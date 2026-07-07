function initHeroSlideSettings() {
    const list = document.querySelector('[data-hero-slide-list]');
    const addButton = document.querySelector('[data-add-hero-slide]');
    const template = document.querySelector('[data-hero-slide-template]');

    if (!list || !addButton) {
        return;
    }

    const fallbackImage = '/assets/img/ZMoon.png';

    const refresh = () => {
        const items = Array.from(list.querySelectorAll('[data-hero-slide-item]'));

        items.forEach((item, index) => {
            item.querySelectorAll('label[for], input[id]').forEach((node) => {
                const attr = node.tagName === 'LABEL' ? 'for' : 'id';
                const value = node.getAttribute(attr) || '';
                node.setAttribute(attr, value.replace(/hero_slide_(title|link|image)_\d+/, `hero_slide_$1_${index}`));
            });

            const removeButton = item.querySelector('[data-remove-hero-slide]');
            if (removeButton) {
                removeButton.hidden = items.length <= 1;
            }
        });
    };

    const createItem = () => {
        const source = template && template.content
            ? template.content.querySelector('[data-hero-slide-item]')
            : list.querySelector('[data-hero-slide-item]');
        if (!source) {
            return null;
        }

        const item = source.cloneNode(true);
        const preview = item.querySelector('.admin-slide-preview img');
        const existing = item.querySelector('input[name="hero_slide_existing[]"]');
        const title = item.querySelector('input[name="hero_slide_title[]"]');
        const link = item.querySelector('input[name="hero_slide_link[]"]');
        const file = item.querySelector('input[name="hero_slide_image[]"]');
        const hint = item.querySelector('.admin-form-hint');

        if (preview) {
            preview.src = fallbackImage;
        }
        if (existing) {
            existing.value = '';
        }
        if (title) {
            title.value = '';
        }
        if (link) {
            link.value = '/';
        }
        if (file) {
            file.value = '';
        }
        if (hint) {
            hint.textContent = '';
        }

        return item;
    };

    addButton.addEventListener('click', () => {
        const item = createItem();
        if (!item) {
            return;
        }

        list.appendChild(item);
        refresh();

        const firstInput = item.querySelector('input[name="hero_slide_title[]"]');
        if (firstInput) {
            firstInput.focus();
        }
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-hero-slide]');
        if (!button) {
            return;
        }

        const item = button.closest('[data-hero-slide-item]');
        if (!item || list.querySelectorAll('[data-hero-slide-item]').length <= 1) {
            return;
        }

        item.remove();
        refresh();
    });

    refresh();
}

function initAdminProfilePanels() {
    const root = document.querySelector('[data-profile-panel-root]');
    if (!root) {
        return;
    }

    const tabs = Array.from(root.querySelectorAll('[data-profile-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-profile-panel]'));
    const track = root.querySelector('[data-profile-panel-track]');
    const windowEl = root.querySelector('.admin-profile-panel-window');

    if (!tabs.length || !panels.length || !track) {
        return;
    }

    const setPanelHeight = () => {
        if (!windowEl) {
            return;
        }

        const activePanel = panels.find((panel) => panel.classList.contains('is-active')) || panels[0];
        if (activePanel) {
            window.requestAnimationFrame(() => {
                const panelHeight = `${activePanel.scrollHeight}px`;
                windowEl.style.setProperty('--profile-panel-height', panelHeight);
                windowEl.style.height = panelHeight;
            });
        }
    };

    const activate = (key) => {
        const index = Math.max(0, panels.findIndex((panel) => panel.getAttribute('data-profile-panel') === key));
        const activeKey = panels[index] ? panels[index].getAttribute('data-profile-panel') : panels[0].getAttribute('data-profile-panel');

        root.style.setProperty('--profile-panel-index', String(index));

        tabs.forEach((tab) => {
            const isActive = tab.getAttribute('data-profile-tab') === activeKey;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.tabIndex = isActive ? 0 : -1;
        });

        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-profile-panel') === activeKey;
            panel.classList.toggle('is-active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            panel.toggleAttribute('inert', !isActive);
        });

        window.requestAnimationFrame(setPanelHeight);
    };

    tabs.forEach((tab, tabIndex) => {
        tab.addEventListener('click', () => activate(tab.getAttribute('data-profile-tab') || ''));
        tab.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                return;
            }

            event.preventDefault();
            const nextIndex = event.key === 'ArrowRight'
                ? (tabIndex + 1) % tabs.length
                : (tabIndex - 1 + tabs.length) % tabs.length;
            tabs[nextIndex].focus();
            activate(tabs[nextIndex].getAttribute('data-profile-tab') || '');
        });
    });

    const nameInput = root.querySelector('[data-profile-name-input]');
    const mottoInput = root.querySelector('[data-profile-motto-input]');
    const previewName = root.querySelector('[data-profile-preview-name]');
    const previewMotto = root.querySelector('[data-profile-preview-motto]');
    const copyInputs = Array.from(root.querySelectorAll('[data-profile-copy-input]'));
    const copyPreviews = Array.from(root.querySelectorAll('[data-profile-preview-copy]'));
    const homeCoverInput = root.querySelector('[data-profile-home-cover-input]');
    const homeCoverPreview = root.querySelector('[data-profile-home-cover-preview]');
    const homeCoverPath = root.querySelector('[data-profile-home-cover-path]');
    let homeCoverPreviewUrl = '';

    const refreshPreview = () => {
        if (nameInput && previewName) {
            const name = nameInput.value.trim();
            previewName.textContent = name !== '' ? name : '管理员';
        }
        if (mottoInput && previewMotto) {
            const motto = mottoInput.value.trim();
            previewMotto.textContent = motto !== '' ? motto : '把日常里的灵感，慢慢写成光。';
        }
        copyInputs.forEach((input) => {
            const label = input.getAttribute('data-profile-copy-input') || '';
            const preview = copyPreviews.find((node) => node.getAttribute('data-profile-preview-copy') === label);
            if (preview) {
                preview.classList.toggle('is-empty', input.value.trim() === '');
            }
        });
        setPanelHeight();
    };

    [nameInput, mottoInput, ...copyInputs].forEach((input) => {
        if (input) {
            input.addEventListener('input', refreshPreview);
        }
    });

    if (homeCoverInput && homeCoverPreview) {
        homeCoverInput.addEventListener('change', () => {
            const file = homeCoverInput.files && homeCoverInput.files[0] ? homeCoverInput.files[0] : null;

            if (homeCoverPreviewUrl !== '') {
                URL.revokeObjectURL(homeCoverPreviewUrl);
                homeCoverPreviewUrl = '';
            }

            if (!file) {
                return;
            }

            homeCoverPreviewUrl = URL.createObjectURL(file);
            homeCoverPreview.src = homeCoverPreviewUrl;
            homeCoverPreview.addEventListener('load', () => {
                if (homeCoverPreviewUrl !== '') {
                    URL.revokeObjectURL(homeCoverPreviewUrl);
                    homeCoverPreviewUrl = '';
                }
            }, { once: true });

            if (homeCoverPath) {
                homeCoverPath.textContent = file.name;
            }
            setPanelHeight();
        });
    }

    window.addEventListener('resize', setPanelHeight);
    activate(tabs.find((tab) => tab.classList.contains('is-active'))?.getAttribute('data-profile-tab') || tabs[0].getAttribute('data-profile-tab') || '');
    refreshPreview();
}
function initAdminProfileAvatarPicker() {
    document.querySelectorAll('.admin-profile-avatar-picker').forEach((picker) => {
        const input = picker.querySelector('.admin-profile-avatar-input');
        const preview = picker.querySelector('.admin-profile-avatar');
        const form = picker.closest('.admin-profile-form');
        const action = picker.querySelector('.admin-profile-avatar-action');

        if (!input || !preview || !form) {
            return;
        }

        let previewUrl = '';
        let uploadRunning = false;
        const defaultActionText = action ? action.textContent : '';

        const setPickerBusy = (isBusy) => {
            uploadRunning = isBusy;
            input.disabled = isBusy;
            picker.classList.toggle('is-uploading', isBusy);
            picker.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            if (action) {
                action.textContent = isBusy ? '上传中...' : defaultActionText;
            }
        };

        input.addEventListener('change', async () => {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file || !file.type || !file.type.startsWith('image/')) {
                return;
            }
            if (uploadRunning) {
                return;
            }

            const previousPreviewSrc = preview.getAttribute('src') || preview.src || '';

            if (previewUrl !== '') {
                URL.revokeObjectURL(previewUrl);
            }

            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.addEventListener('load', () => {
                if (previewUrl !== '') {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = '';
                }
            }, { once: true });

            const formData = new FormData();
            const usernameInput = form.querySelector('input[name="username"]');
            const profileName = (form.querySelector('.admin-profile-name')?.textContent || '').trim();
            formData.append('username', profileName || (usernameInput ? usernameInput.value || usernameInput.defaultValue : ''));
            formData.append(input.name || 'profile_avatar_file', file);
            setPickerBusy(true);

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
                const message = ok ? '头像已更新' : (payload && payload.message ? payload.message : '头像上传失败');

                if (ok) {
                    updateAdminProfileState(payload || {});
                    input.value = '';
                } else if (previousPreviewSrc !== '') {
                    preview.src = previousPreviewSrc;
                }

                if (window.showAdminToast) {
                    window.showAdminToast(message, ok ? 'success' : 'error');
                }

                const redirectUrl = payload && (payload.redirect || payload.redirect_url || payload.login_url);
                if (redirectUrl) {
                    redirectAfterAdminToast(String(redirectUrl));
                }
            } catch (error) {
                if (previousPreviewSrc !== '') {
                    preview.src = previousPreviewSrc;
                }
                if (window.showAdminToast) {
                    window.showAdminToast('头像上传失败，请稍后重试', 'error');
                }
            } finally {
                setPickerBusy(false);
            }
        });
    });
}

initHeroSlideSettings();
initAdminProfilePanels();
initAdminProfileAvatarPicker();
