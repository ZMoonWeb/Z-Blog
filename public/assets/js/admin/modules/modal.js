function initCategoryModalEditor() {
    const modal = document.querySelector('[data-category-modal]');

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-category-form]');
    const title = modal.querySelector('[data-category-modal-title]');
    const desc = modal.querySelector('[data-category-modal-desc]');
    const nameInput = modal.querySelector('[data-category-name-input]');
    const descriptionInput = modal.querySelector('[data-category-description-input]');
    const submitButton = modal.querySelector('[data-category-submit]');
    const createAction = modal.getAttribute('data-category-create-action') || '/admin/categories/create';
    const editTemplate = modal.getAttribute('data-category-edit-action-template') || '/admin/categories/__ID__/edit';

    if (!form || !nameInput || !descriptionInput) {
        return;
    }

    const openModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-modal-open');
    };

    const setCreateMode = () => {
        modal.setAttribute('data-category-mode', 'create');
        modal.removeAttribute('data-category-current-id');
        form.action = createAction;
        form.setAttribute('action', createAction);
        nameInput.value = '';
        descriptionInput.value = '';

        if (title) title.textContent = '新增分类';
        if (desc) desc.textContent = '创建一个新的文章分类。';
        if (submitButton) submitButton.textContent = '创建分类';
    };

    const setEditMode = (trigger) => {
        const id = trigger.getAttribute('data-category-id') || '';
        const editAction = editTemplate.replace('__ID__', encodeURIComponent(id));

        modal.setAttribute('data-category-mode', 'edit');
        modal.setAttribute('data-category-current-id', id);
        form.action = editAction;
        form.setAttribute('action', editAction);
        nameInput.value = trigger.getAttribute('data-category-name') || '';
        descriptionInput.value = trigger.getAttribute('data-category-description') || '';

        if (title) title.textContent = '编辑分类';
        if (desc) desc.textContent = '修改当前分类名称和描述。';
        if (submitButton) submitButton.textContent = '保存分类';
    };

    document.querySelectorAll('[data-category-create]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setCreateMode();
            openModal();
        });
    });

    document.querySelectorAll('[data-category-edit]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setEditMode(trigger);
            openModal();
        });
    });

    form.addEventListener('submit', () => {
        if (modal.getAttribute('data-category-mode') !== 'edit') {
            form.action = createAction;
            form.setAttribute('action', createAction);
            return;
        }

        const id = modal.getAttribute('data-category-current-id') || '';
        const editAction = editTemplate.replace('__ID__', encodeURIComponent(id));
        form.action = editAction;
        form.setAttribute('action', editAction);
    });
}

function initDeleteConfirmModal(options) {
    const modal = document.querySelector(options.modalSelector);
    if (!modal) {
        return;
    }

    const desc = modal.querySelector(options.descSelector);
    const confirmButton = modal.querySelector(options.confirmSelector);
    let pendingForm = null;

    if (!confirmButton) {
        return;
    }

    const openModal = (form) => {
        pendingForm = form;
        const name = form.getAttribute(options.nameAttr) || options.defaultName;

        if (desc) {
            const descFromForm = options.descAttr ? form.getAttribute(options.descAttr) : '';
            desc.textContent = descFromForm || options.buildDesc(name);
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-modal-open');
    };

    document.querySelectorAll(options.formSelector).forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.getAttribute(options.confirmedAttr) === 'true') {
                return;
            }

            event.preventDefault();
            openModal(form);
        });
    });

    confirmButton.addEventListener('click', () => {
        if (!pendingForm) {
            return;
        }

        pendingForm.setAttribute(options.confirmedAttr, 'true');
        if (typeof pendingForm.requestSubmit === 'function') {
            pendingForm.requestSubmit();
        } else {
            pendingForm.submit();
        }
    });
}

function initCategoryDeleteModal() {
    initDeleteConfirmModal({
        modalSelector: '[data-category-delete-modal]',
        descSelector: '[data-category-delete-desc]',
        confirmSelector: '[data-category-delete-confirm]',
        formSelector: '[data-category-delete-form]',
        nameAttr: 'data-category-delete-name',
        confirmedAttr: 'data-category-delete-confirmed',
        defaultName: '这个分类',
        buildDesc: (name) => `删除「${name}」后，分类下的文章会设为未分类。`,
    });
}

function initAnnouncementDeleteModal() {
    initDeleteConfirmModal({
        modalSelector: '[data-announcement-delete-modal]',
        descSelector: '[data-announcement-delete-desc]',
        confirmSelector: '[data-announcement-delete-confirm]',
        formSelector: '[data-announcement-delete-form]',
        nameAttr: 'data-announcement-delete-name',
        confirmedAttr: 'data-announcement-delete-confirmed',
        defaultName: '这条公告',
        buildDesc: () => '删除后，该公告将从前台公告页移除，无法恢复。',
    });
}

function initGuestbookConfirmModal() {
    const modal = document.querySelector('[data-guestbook-confirm-modal]');
    if (!modal) {
        return;
    }

    const title = modal.querySelector('[data-guestbook-confirm-title]');
    const desc = modal.querySelector('[data-guestbook-confirm-desc]');
    const confirmButton = modal.querySelector('[data-guestbook-confirm-confirm]');
    let pendingForm = null;

    if (!confirmButton) {
        return;
    }

    const openModal = (form) => {
        pendingForm = form;
        const name = form.getAttribute('data-guestbook-confirm-name') || '此操作';
        const descText = form.getAttribute('data-guestbook-confirm-desc') || '请确认是否继续。';
        const variant = form.getAttribute('data-guestbook-confirm-variant') === 'warning' ? 'warning' : 'danger';

        if (title) {
            title.textContent = name;
        }
        if (desc) {
            desc.textContent = descText;
        }
        if (confirmButton) {
            confirmButton.classList.toggle('admin-btn-danger', variant === 'danger');
            confirmButton.classList.toggle('admin-btn-warning', variant === 'warning');
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-modal-open');
    };

    document.querySelectorAll('[data-guestbook-confirm-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.getAttribute('data-guestbook-confirm-confirmed') === 'true') {
                return;
            }

            event.preventDefault();
            openModal(form);
        });
    });

    confirmButton.addEventListener('click', () => {
        if (!pendingForm) {
            return;
        }

        pendingForm.setAttribute('data-guestbook-confirm-confirmed', 'true');
        if (typeof pendingForm.requestSubmit === 'function') {
            pendingForm.requestSubmit();
        } else {
            pendingForm.submit();
        }
    });
}

function initAdminModals() {
    const openModal = (modal) => {
        if (!modal) return;

        if (modal.adminCloseTimer) {
            window.clearTimeout(modal.adminCloseTimer);
            modal.adminCloseTimer = null;
        }

        modal.classList.remove('is-closing');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-modal-open');

        if (!modal.matches(':has(.admin-guestbook-detail-modal)')) {
            const focusTarget = modal.querySelector('input, textarea, select, button, a');
            if (focusTarget) {
                window.setTimeout(() => focusTarget.focus(), 80);
            }
        }
    };

    const closeModal = (modal) => {
        if (!modal || modal.classList.contains('is-closing')) return;

        const canAnimate = window.matchMedia('(prefers-reduced-motion: no-preference)').matches
            && (
                (document.body.classList.contains('admin-activity-index-page') && modal.querySelector('.admin-interaction-detail-modal'))
                || modal.querySelector('.admin-update-notes-panel')
            );

        const finishClose = () => {
            if (modal.adminCloseTimer) {
                window.clearTimeout(modal.adminCloseTimer);
                modal.adminCloseTimer = null;
            }

            modal.classList.remove('is-open', 'is-closing');
            modal.setAttribute('aria-hidden', 'true');

            if (!document.querySelector('[data-admin-modal].is-open')) {
                document.body.classList.remove('admin-modal-open');
            }
        };

        if (!canAnimate) {
            finishClose();
            return;
        }

        modal.classList.add('is-closing');
        modal.setAttribute('aria-hidden', 'true');
        modal.adminCloseTimer = window.setTimeout(finishClose, 240);
    };

    document.querySelectorAll('[data-admin-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openModal(document.getElementById(trigger.getAttribute('data-admin-modal-open')));
        });
    });

    document.querySelectorAll('[data-admin-modal]').forEach((modal) => {
        if (modal.classList.contains('is-open')) {
            document.body.classList.add('admin-modal-open');
        }

        modal.addEventListener('click', (event) => {
            const closeTrigger = event.target.closest('[data-admin-modal-close]');
            if (!closeTrigger) return;

            if (closeTrigger.tagName === 'A') {
                return;
            }

            event.preventDefault();
            closeModal(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        const modal = document.querySelector('[data-admin-modal].is-open');
        closeModal(modal);
    });
}

initCategoryModalEditor();
initCategoryDeleteModal();
initAnnouncementDeleteModal();
initGuestbookConfirmModal();
initAdminModals();
