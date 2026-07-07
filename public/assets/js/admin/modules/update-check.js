function readAdminCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const metaToken = meta ? String(meta.getAttribute('content') || '') : '';
    if (metaToken !== '') {
        return metaToken;
    }

    const input = document.querySelector('input[name="_csrf"]');
    return input ? String(input.value || '') : '';
}

function adminCsrfHeaders() {
    const token = readAdminCsrfToken();
    return token !== '' ? { 'X-CSRF-Token': token } : {};
}

async function readAdminJsonResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        try {
            return await response.json();
        } catch (error) {
            return {
                success: false,
                type: 'error',
                message: '提交失败，服务器返回异常',
            };
        }
    }

    const text = await response.text();
    return {
        success: false,
        type: 'error',
        message: text.trim() ? '提交失败，请检查表单内容' : '提交失败，请稍后重试',
    };
}

function isAdminPayloadOk(response, payload) {
    if (!response || !response.ok || !payload) {
        return false;
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'success')) {
        return payload.success === true || payload.success === 1 || String(payload.success).toLowerCase() === 'true';
    }

    return payload.ok !== false;
}

function initAdminUpdateCheck() {
    const card = document.querySelector('[data-update-check-card]');
    const notesModal = document.getElementById('admin-update-notes-modal');
    if (!card || !notesModal) {
        return;
    }

    if (card.getAttribute('data-update-check-enabled') === 'false') {
        return;
    }

    const notesVersion = notesModal.querySelector('[data-update-notes-version]');
    const notesSummary = notesModal.querySelector('[data-update-notes-summary]');
    const notesContent = notesModal.querySelector('[data-update-notes-content]');
    const downloadLink = notesModal.querySelector('[data-update-download]');
    const noRemindCheckbox = notesModal.querySelector('[data-update-no-remind-today]');
    const checkButton = card.querySelector('[data-update-check]');
    let checking = false;
    let earlyCheckPromise = window.__zblogAdminUpdateCheckEarly || null;

    const setText = (node, value) => {
        if (node) {
            node.textContent = String(value || '');
        }
    };

    const payloadNotes = (payload) => {
        if (payload && Array.isArray(payload.update_notes)) {
            return payload.update_notes.map((item) => String(item || '').trim()).filter(Boolean);
        }
        if (payload && Array.isArray(payload.notes)) {
            return payload.notes.map((item) => String(item || '').trim()).filter(Boolean);
        }
        return [];
    };

    const releaseUrlFromPayload = (payload) => {
        const value = payload && (payload.release_url || payload.github_url || payload.repo_url)
            ? String(payload.release_url || payload.github_url || payload.repo_url).trim()
            : '';
        return value !== '' ? value : 'https://github.com';
    };

    const noRemindStorageKey = 'zblog-admin-update-no-remind-today';

    const todayKey = () => {
        const date = new Date();
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const readNoRemindToday = () => {
        try {
            const raw = window.localStorage.getItem(noRemindStorageKey);
            if (!raw) {
                return null;
            }

            const value = JSON.parse(raw);
            return value && typeof value === 'object' ? value : null;
        } catch (error) {
            return null;
        }
    };

    const shouldSkipToday = (version) => {
        const latestVersion = String(version || '').trim();
        const stored = readNoRemindToday();
        return latestVersion !== ''
            && stored
            && stored.version === latestVersion
            && stored.date === todayKey();
    };

    const rememberNoRemindToday = () => {
        if (!noRemindCheckbox || !noRemindCheckbox.checked) {
            return;
        }

        const version = String(notesModal.getAttribute('data-update-version') || '').trim();
        if (version === '') {
            return;
        }

        try {
            window.localStorage.setItem(noRemindStorageKey, JSON.stringify({
                version,
                date: todayKey(),
            }));
        } catch (error) {}
    };

    const compareVersions = (left, right) => {
        const normalize = (value) => String(value || '')
            .trim()
            .replace(/^[vV]\s*/, '')
            .split(/[.+-]/)
            .map((part) => Number.parseInt(part, 10))
            .map((part) => (Number.isFinite(part) ? part : 0));
        const a = normalize(left);
        const b = normalize(right);
        const length = Math.max(a.length, b.length, 3);

        for (let index = 0; index < length; index += 1) {
            const diff = (a[index] || 0) - (b[index] || 0);
            if (diff !== 0) {
                return diff;
            }
        }

        return 0;
    };

    const boolValue = (value) => (
        value === true || value === 1 || ['true', '1'].includes(String(value).trim().toLowerCase())
    );

    const isUpdateAvailablePayload = (payload) => {
        if (!payload) {
            return false;
        }

        if (Object.prototype.hasOwnProperty.call(payload, 'update_available')) {
            return boolValue(payload.update_available);
        }

        if (Object.prototype.hasOwnProperty.call(payload, 'is_latest')) {
            return !boolValue(payload.is_latest);
        }

        const latestVersion = payload.latest_version || payload.version || '';
        const currentVersion = payload.current_version || card.getAttribute('data-current-version') || '';
        return latestVersion !== '' && currentVersion !== '' && compareVersions(latestVersion, currentVersion) > 0;
    };


    const openUpdateNotesModal = () => {
        if (notesModal.adminCloseTimer) {
            window.clearTimeout(notesModal.adminCloseTimer);
            notesModal.adminCloseTimer = null;
        }

        notesModal.classList.remove('is-closing');
        notesModal.classList.add('is-open');
        notesModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-modal-open');

        const focusTarget = downloadLink || notesModal.querySelector('[data-admin-modal-close]');
        if (focusTarget) {
            window.setTimeout(() => focusTarget.focus(), 80);
        }
    };

    const renderUpdateNotice = (payload) => {
        if (!notesContent) {
            return;
        }

        const latestVersion = payload && payload.latest_version ? String(payload.latest_version).trim() : '';
        const currentVersion = payload && payload.current_version ? String(payload.current_version).trim() : (card.getAttribute('data-current-version') || '');
        const notes = payloadNotes(payload);
        const notesError = payload && payload.update_notes_error ? String(payload.update_notes_error).trim() : '';
        const releaseUrl = releaseUrlFromPayload(payload);

        notesModal.setAttribute('data-update-version', latestVersion);
        if (noRemindCheckbox) {
            noRemindCheckbox.checked = false;
        }
        setText(notesVersion, latestVersion !== '' ? `v${latestVersion}` : '新版本');
        setText(notesSummary, currentVersion !== '' && latestVersion !== ''
            ? `当前版本 v${currentVersion}，检测到 v${latestVersion}。可以前往 GitHub 下载新版。`
            : '检测到新版本，可以前往 GitHub 下载。');

        if (downloadLink) {
            downloadLink.href = releaseUrl;
        }

        notesContent.innerHTML = '';
        if (notes.length > 0) {
            const list = document.createElement('ul');
            list.className = 'admin-update-notes-list';
            notes.forEach((note) => {
                const item = document.createElement('li');
                const marker = document.createElement('span');
                const text = document.createElement('p');
                marker.setAttribute('aria-hidden', 'true');
                text.textContent = note;
                item.append(marker, text);
                list.appendChild(item);
            });
            notesContent.appendChild(list);
            return;
        }

        const empty = document.createElement('p');
        empty.className = 'admin-update-notes-empty';
        empty.textContent = notesError !== '' ? `更新说明获取失败：${notesError}` : '暂无更新说明，请前往 GitHub 查看完整发布内容。';
        notesContent.appendChild(empty);
    };

    const readEarlyCheckResult = async () => {
        if (!earlyCheckPromise) {
            return null;
        }

        const promise = earlyCheckPromise;
        earlyCheckPromise = null;

        try {
            const result = await promise;
            if (!result || !result.payload) {
                return null;
            }

            return {
                response: {
                    ok: result.ok === true,
                    status: Number(result.status || 0),
                },
                payload: result.payload,
            };
        } catch (error) {
            return null;
        }
    };

    const fetchUpdateNotes = async (latestVersion, basePayload) => {
        const version = String(latestVersion || '').trim();
        const fallbackPayload = Object.assign({}, basePayload || {}, {
            latest_version: version,
            update_notes: [],
            update_notes_error: '',
        });

        if (version === '') {
            fallbackPayload.update_notes_error = '缺少目标版本号';
            return fallbackPayload;
        }

        try {
            const body = new FormData();
            body.append('version', version);
            const response = await fetch(card.getAttribute('data-update-notes-url') || '/admin/api/update-notes', {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...adminCsrfHeaders(),
                },
            });
            const payload = await readAdminJsonResponse(response);
            if (!isAdminPayloadOk(response, payload)) {
                fallbackPayload.update_notes_error = payload && payload.message ? String(payload.message) : '更新说明获取失败';
                return fallbackPayload;
            }
            return Object.assign(fallbackPayload, payload || {}, {
                latest_version: version,
                update_notes: payloadNotes(payload),
                update_notes_error: '',
                release_url: (payload && payload.release_url) || fallbackPayload.release_url || '',
            });
        } catch (error) {
            fallbackPayload.update_notes_error = '更新说明获取失败，请稍后重试';
            return fallbackPayload;
        }
    };

    const runCheck = async () => {
        if (checking) {
            return;
        }
        checking = true;
        window.__zblogAdminUpdatePending = true;
        if (checkButton) {
            checkButton.disabled = true;
            checkButton.setAttribute('aria-busy', 'true');
        }

        try {
            const earlyResult = await readEarlyCheckResult();
            let response;
            let payload;

            if (earlyResult) {
                response = earlyResult.response;
                payload = earlyResult.payload;
            } else {
                response = await fetch(card.getAttribute('data-update-check-url') || '/admin/api/check-update', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...adminCsrfHeaders(),
                    },
                });
                payload = await readAdminJsonResponse(response);
            }

            if (!isAdminPayloadOk(response, payload) || !isUpdateAvailablePayload(payload)) {
                return;
            }

            const latestVersion = payload.latest_version ? String(payload.latest_version).trim() : '';
            if (shouldSkipToday(latestVersion)) {
                return;
            }

            const notesPayload = await fetchUpdateNotes(latestVersion, payload);
            renderUpdateNotice(notesPayload);
            openUpdateNotesModal();
        } catch (error) {
            // 自动检测失败时保持静默，避免进入后台时打扰操作。
        } finally {
            checking = false;
            window.__zblogAdminUpdatePending = false;
            if (checkButton) {
                checkButton.disabled = false;
                checkButton.removeAttribute('aria-busy');
            }
        }
    };


    notesModal.addEventListener('click', (event) => {
        const closeTrigger = event.target.closest('[data-admin-modal-close]');
        if (closeTrigger && closeTrigger.tagName !== 'A') {
            rememberNoRemindToday();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && notesModal.classList.contains('is-open')) {
            rememberNoRemindToday();
        }
    });

    if (downloadLink) {
        downloadLink.addEventListener('click', rememberNoRemindToday);
    }

    if (checkButton) {
        checkButton.addEventListener('click', runCheck);
    }

    if (card.getAttribute('data-update-auto-check') === 'true') {
        window.__zblogAdminUpdateReady = runCheck().catch(() => {});
    }
}

initAdminUpdateCheck();
