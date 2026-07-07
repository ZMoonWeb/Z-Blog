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

function togglePassword() {
    const input = document.getElementById('password');
    const button = document.querySelector('.admin-password-toggle');
    const eyeOpen = document.querySelector('.eye-open');
    const eyeClosed = document.querySelector('.eye-closed');

    if (!input || !button || !eyeOpen || !eyeClosed) return;

    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
        button.setAttribute('aria-label', '隐藏密码');
        button.setAttribute('aria-pressed', 'true');
    } else {
        input.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
        button.setAttribute('aria-label', '显示密码');
        button.setAttribute('aria-pressed', 'false');
    }
}

function initAdminLoginLoading() {
    const body = document.body;

    if (!body || !body.classList.contains('admin-login-page')) {
        return;
    }

    const revealLogin = () => {
        body.classList.remove('admin-login-loading');
        body.classList.add('admin-login-ready');
    };

    if (document.readyState === 'complete') {
        revealLogin();
    } else {
        window.addEventListener('load', revealLogin, { once: true });
    }
}

function initAdminLoginSubmit() {
    const form = document.querySelector('.admin-login-form');
    const button = document.querySelector('[data-login-submit]');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (button.classList.contains('is-loading')) {
            event.preventDefault();
            return;
        }

        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        window.setTimeout(() => {
            button.disabled = true;
        }, 0);
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
            button.disabled = false;
        }
    });
}

function initAdminToasts() {
    let container = document.querySelector('[data-admin-toast-container]');

    if (!container) {
        container = document.createElement('div');
        container.className = 'admin-toast-container';
        container.setAttribute('data-admin-toast-container', '');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
    }

    const showToast = (message, type = 'success') => {
        const text = String(message || '').trim();
        if (text === '') {
            return;
        }

        const normalizedType = String(type || 'success');
        const toastType = ['success', 'error', 'warning'].includes(normalizedType) ? normalizedType : 'success';
        const toast = document.createElement('div');
        const messageNode = document.createElement('span');
        const countdown = document.createElement('span');

        toast.className = `admin-toast admin-toast-${toastType}`;
        toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');
        toast.style.setProperty('--admin-toast-duration', '3000ms');

        messageNode.className = 'admin-toast-message';
        messageNode.textContent = text;

        countdown.className = 'admin-toast-countdown';
        countdown.setAttribute('aria-hidden', 'true');

        toast.appendChild(messageNode);
        toast.appendChild(countdown);
        container.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add('is-hiding');
        }, 3000);

        window.setTimeout(() => {
            toast.remove();
        }, 3300);
    };

    container.querySelectorAll('[data-admin-toast]').forEach((seed) => {
        showToast(seed.getAttribute('data-admin-toast-message') || '', seed.getAttribute('data-admin-toast-type') || 'success');
        seed.remove();
    });

    document.querySelectorAll('.admin-flash').forEach((flash) => {
        if (flash.closest('[data-admin-toast-container]')) {
            return;
        }

        const type = flash.classList.contains('admin-flash-error') ? 'error' : 'success';
        const parts = Array.from(flash.querySelectorAll('div'))
            .map((node) => (node.textContent || '').trim())
            .filter(Boolean);
        const message = parts.length > 0 ? parts.join('；') : (flash.textContent || '').trim();
        showToast(message, type);
        flash.remove();
    });

    window.showAdminToast = showToast;
}

function buildAdminFormData(form, submitter) {
    if (submitter && typeof FormData === 'function') {
        try {
            return new FormData(form, submitter);
        } catch (error) {
            const formData = new FormData(form);
            if (submitter.name && !submitter.disabled) {
                formData.append(submitter.name, submitter.value || '');
            }
            return formData;
        }
    }

    return new FormData(form);
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

function setAdminSubmitButtonBusy(button, isBusy, busyText = '提交中...') {
    if (!button) {
        return () => {};
    }

    const usesValue = Object.prototype.hasOwnProperty.call(button, 'value') && button.tagName === 'INPUT';
    const originalText = usesValue ? button.value : button.textContent;

    button.disabled = isBusy;
    button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    if (usesValue) {
        button.value = busyText;
    } else {
        button.textContent = busyText;
    }

    return () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (usesValue) {
            button.value = originalText;
        } else {
            button.textContent = originalText;
        }
    };
}

function updateAdminProfileState(payload) {
    const profile = payload && payload.profile ? payload.profile : {};
    const settings = payload && payload.settings ? payload.settings : {};
    const username = profile.username ? String(profile.username) : '';
    const avatar = profile.avatar ? String(profile.avatar) : (settings.profile_avatar ? String(settings.profile_avatar) : '');

    if (username !== '') {
        document.querySelectorAll('.admin-profile-name').forEach((node) => {
            node.textContent = username;
        });
        document.querySelectorAll('.admin-site-user').forEach((node) => {
            node.setAttribute('title', `${username} · 个人资料`);
        });
    }

    if (avatar !== '') {
        document.querySelectorAll('.admin-site-user .admin-user-avatar img, .admin-profile-avatar').forEach((image) => {
            image.src = avatar;
            if (username !== '') {
                image.alt = `${username} 头像`;
            }
        });
    }

    const motto = settings.profile_motto ? String(settings.profile_motto).trim() : '';
    if (motto !== '') {
        document.querySelectorAll('[data-profile-preview-motto]').forEach((node) => {
            node.textContent = motto;
        });
    }

    const homeCover = settings.profile_home_cover ? String(settings.profile_home_cover).trim() : '';
    if (homeCover !== '') {
        document.querySelectorAll('[data-profile-home-cover-preview]').forEach((image) => {
            image.src = homeCover;
        });
        document.querySelectorAll('[data-profile-home-cover-path]').forEach((node) => {
            node.textContent = homeCover;
        });
    }
}

function handleAdminAsyncSuccess(form, payload) {
    if (form.matches('.admin-profile-form')) {
        updateAdminProfileState(payload);
        form.querySelectorAll('input[type="password"]').forEach((input) => {
            input.value = '';
        });
        form.querySelectorAll('input[type="file"]').forEach((input) => {
            input.value = '';
        });
    }
}

function redirectAfterAdminToast(url) {
    if (!url) {
        return;
    }

    window.setTimeout(() => {
        window.location.href = url;
    }, 1100);
}

async function submitAdminFormAsync(form, submitter) {
    if (form.getAttribute('data-admin-async-submitting') === 'true') {
        return;
    }

    form.setAttribute('data-admin-async-submitting', 'true');
    const button = submitter && submitter.matches('button, input[type="submit"]')
        ? submitter
        : form.querySelector('button[type="submit"], input[type="submit"]');
    const formData = buildAdminFormData(form, submitter);
    const restoreButton = setAdminSubmitButtonBusy(button, true, form.matches('.admin-profile-form') ? '保存中...' : '提交中...');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...adminCsrfHeaders(),
            },
        });

        const payload = await readAdminJsonResponse(response);
        const ok = isAdminPayloadOk(response, payload);
        const message = payload && payload.message ? payload.message : (ok ? '操作成功' : '操作失败');

        if (ok) {
            handleAdminAsyncSuccess(form, payload || {});
        }

        if (window.showAdminToast) {
            window.showAdminToast(message, ok ? 'success' : 'error');
        }

        const redirectUrl = payload && (payload.redirect || payload.redirect_url || payload.login_url);
        if (redirectUrl) {
            redirectAfterAdminToast(String(redirectUrl));
        }
    } catch (error) {
        if (window.showAdminToast) {
            window.showAdminToast('提交失败，请稍后重试', 'error');
        }
    } finally {
        restoreButton();
        form.removeAttribute('data-admin-async-submitting');
    }
}

function initAdminAsyncForms() {
    if (document.body && document.body.classList.contains('admin-login-page')) {
        return;
    }

    document.querySelectorAll('form[method]').forEach((form) => {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') {
            return;
        }
        if (form.matches('.admin-login-form') || form.closest('.admin-settings-main')) {
            return;
        }
        if (form.getAttribute('data-admin-async') === 'off') {
            return;
        }

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            event.preventDefault();
            submitAdminFormAsync(form, event.submitter || null);
        });
    });
}

function initAdminSettingsForms() {
    const forms = Array.from(document.querySelectorAll('.admin-settings-main form[action="/admin/settings"]'));

    if (forms.length === 0) {
        return;
    }

    const updateImageSetting = (form, settings, fileName, settingKey) => {
        const input = form.querySelector(`input[type="file"][name="${fileName}"]`);
        const value = settings && settings[settingKey] ? String(settings[settingKey]) : '';

        if (!input || value === '') {
            return;
        }

        const card = input.closest('.admin-upload-card');
        const path = card ? card.querySelector('.admin-upload-path') : null;
        const preview = card ? card.querySelector('.admin-upload-preview') : null;

        if (path) {
            path.textContent = value;
        }
        if (preview) {
            preview.src = value;
        }
        input.value = '';
    };

    const updateSavedState = (form, payload) => {
        const settings = payload && payload.settings ? payload.settings : {};
        const scope = form.querySelector('input[name="settings_scope"]')?.value || '';

        if (scope === 'basic') {
            updateImageSetting(form, settings, 'site_logo_file', 'site_logo');
            updateImageSetting(form, settings, 'site_avatar_file', 'site_avatar');
            updateImageSetting(form, settings, 'profile_avatar_file', 'profile_avatar');
            updateImageSetting(form, settings, 'profile_cover_file', 'profile_cover');
            updateAdminProfileState({ settings });
        } else if (scope === 'about') {
            updateImageSetting(form, settings, 'about_avatar_file', 'about_avatar');
            updateImageSetting(form, settings, 'about_cover_file', 'about_cover');
        } else if (scope === 'footer') {
            updateImageSetting(form, settings, 'footer_logo_file', 'footer_logo');
        } else if (scope === 'home' && Array.isArray(payload.heroSlides)) {
            const items = Array.from(form.querySelectorAll('[data-hero-slide-item]'));
            payload.heroSlides.forEach((slide, index) => {
                const item = items[index];
                const image = slide && slide.image_url ? String(slide.image_url) : '';

                if (!item || image === '') {
                    return;
                }

                const existing = item.querySelector('input[name="hero_slide_existing[]"]');
                const preview = item.querySelector('.admin-slide-preview img');
                const hint = item.querySelector('.admin-form-hint');
                const file = item.querySelector('input[name="hero_slide_image[]"]');

                if (existing) existing.value = image;
                if (preview) preview.src = image;
                if (hint) hint.textContent = image;
                if (file) file.value = '';
            });
        }
    };

    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const originalText = button ? button.textContent : '';

            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = '保存中...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...adminCsrfHeaders(),
                    },
                });

                const payload = await readAdminJsonResponse(response);
                const ok = isAdminPayloadOk(response, payload);
                const message = payload && payload.message ? payload.message : (ok ? '保存成功' : '保存失败');

                if (ok) {
                    updateSavedState(form, payload);
                }

                if (window.showAdminToast) {
                    window.showAdminToast(message, ok ? 'success' : 'error');
                }

                const redirectUrl = payload && (payload.redirect || payload.redirect_url || payload.login_url);
                if (redirectUrl) {
                    redirectAfterAdminToast(String(redirectUrl));
                }
            } catch (error) {
                if (window.showAdminToast) {
                    window.showAdminToast('保存失败，请稍后重试', 'error');
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    button.textContent = originalText;
                }
            }
        });
    });
}

initAdminLoginLoading();
initAdminLoginSubmit();
initAdminToasts();
initAdminAsyncForms();
initAdminSettingsForms();
