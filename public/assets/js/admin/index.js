const ADMIN_THEME_STORAGE_KEY = 'zblog-admin-theme';

function getSystemAdminTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function readStoredAdminTheme() {
    try {
        const theme = window.localStorage.getItem(ADMIN_THEME_STORAGE_KEY);
        return theme === 'light' || theme === 'dark' ? theme : null;
    } catch (error) {
        return null;
    }
}

function applyAdminTheme(theme, source = 'manual') {
    const root = document.documentElement;
    const nextTheme = theme === 'dark' ? 'dark' : 'light';

    root.setAttribute('data-admin-theme', nextTheme);
    root.setAttribute('data-theme', nextTheme);
    root.setAttribute('data-admin-theme-source', source);
    root.setAttribute('data-theme-source', source);
    root.style.colorScheme = nextTheme === 'dark' ? 'dark' : 'only light';

    document.querySelectorAll('[data-admin-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', nextTheme === 'dark' ? 'true' : 'false');
        button.setAttribute('aria-label', nextTheme === 'dark' ? '切换到浅色模式' : '切换到暗色模式');
    });
}

function syncAdminThemeFromFrontTheme() {
    const root = document.documentElement;
    const sync = () => {
        const frontTheme = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const frontSource = root.getAttribute('data-theme-source') || 'system';

        root.setAttribute('data-admin-theme', frontTheme);
        root.setAttribute('data-admin-theme-source', frontSource);
    };

    sync();

    if (typeof MutationObserver === 'function') {
        const observer = new MutationObserver(sync);
        observer.observe(root, {
            attributes: true,
            attributeFilter: ['data-theme', 'data-theme-source'],
        });
    }
}

function animateAdminThemeChange(nextTheme, origin) {
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const root = document.documentElement;

    if (reduceMotion) {
        applyAdminTheme(nextTheme, 'manual');
        return;
    }

    if (origin && typeof document.startViewTransition === 'function') {
        const rect = origin.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const radius = Math.ceil(Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y)));
        const clipPath = [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`];

        const transition = document.startViewTransition(() => {
            applyAdminTheme(nextTheme, 'manual');
        });

        transition.ready.then(() => {
            root.animate({ clipPath }, {
                duration: 620,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                pseudoElement: '::view-transition-new(root)',
            });
        }).catch(() => {});

        return;
    }

    root.classList.add('admin-theme-anim');
    applyAdminTheme(nextTheme, 'manual');
    window.setTimeout(() => {
        root.classList.remove('admin-theme-anim');
    }, 480);
}

function initAdminTheme() {
    if (document.body && document.body.classList.contains('admin-login-page')) {
        syncAdminThemeFromFrontTheme();
        return;
    }

    const storedTheme = readStoredAdminTheme();
    applyAdminTheme(storedTheme || getSystemAdminTheme(), storedTheme ? 'manual' : 'system');

    document.querySelectorAll('[data-admin-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-admin-theme') || getSystemAdminTheme();
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            try {
                window.localStorage.setItem(ADMIN_THEME_STORAGE_KEY, nextTheme);
            } catch (error) {
                // localStorage 不可用时仍然允许本次切换生效。
            }

            animateAdminThemeChange(nextTheme, button);
        });
    });

    if (window.matchMedia) {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const syncSystemTheme = () => {
            if (readStoredAdminTheme()) return;
            applyAdminTheme(media.matches ? 'dark' : 'light', 'system');
        };

        if (media.addEventListener) {
            media.addEventListener('change', syncSystemTheme);
        } else if (media.addListener) {
            media.addListener(syncSystemTheme);
        }
    }
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

function toggleAdminSidebar() {
    const body = document.body;
    const toggle = document.querySelector('.admin-sidebar-toggle');
    const isOpen = body.classList.toggle('admin-sidebar-open');

    if (toggle) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('aria-label', isOpen ? '收起侧边栏' : '展开侧边栏');
    }
}

function closeAdminSidebar() {
    document.body.classList.remove('admin-sidebar-open');

    const toggle = document.querySelector('.admin-sidebar-toggle');
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', '展开侧边栏');
    }
}

function initAdminSidebar() {
    document.querySelectorAll('.admin-nav-item').forEach((item) => {
        item.addEventListener('click', (event) => {
            if (window.__zblogAdminUpdatePending === true) {
                event.preventDefault();
                return;
            }

            if (window.matchMedia && window.matchMedia('(max-width: 980px)').matches) {
                closeAdminSidebar();
            }
            showMainLoader();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAdminSidebar();
        }
    });
}

function showMainLoader() {
    const main = document.querySelector('.admin-main');
    if (!main || main.querySelector('.admin-main-loader')) {
        return;
    }
    main.classList.add('is-loading');
    const loader = document.createElement('div');
    loader.className = 'admin-main-loader';
    loader.innerHTML = '<span class="admin-main-loader-spinner"></span>';
    main.appendChild(loader);
}

function initAdminHeader() {
    const headers = document.querySelectorAll('.admin-shell-header, [data-admin-header]');

    if (headers.length === 0) {
        return;
    }

    const syncHeaderState = () => {
        const isScrolled = window.scrollY > 2;
        headers.forEach((header) => {
            header.classList.toggle('is-scrolled', isScrolled);
        });
    };

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
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

function getEditorMode() {
    const checked = document.querySelector('input[name="content_mode"]:checked');
    return checked ? checked.value : 'markdown';
}

const EDITOR_HEADING_LABELS = {
    1: '一号',
    2: '二号',
    3: '三号',
    4: '四号',
    5: '五号',
    6: '六号',
};

function getEditorHeadingLevel(type) {
    if (type === 'heading') {
        return 2;
    }

    const match = String(type || '').match(/^heading([1-6])$/);
    return match ? Number.parseInt(match[1], 10) : 0;
}

function editorSnippet(type, mode, selectedText) {
    const text = selectedText || '';
    const headingLevel = getEditorHeadingLevel(type);
    const snippetType = headingLevel > 0 ? 'heading' : type;
    const headingText = text || '请输入标题';
    const headingLabel = EDITOR_HEADING_LABELS[headingLevel || 2] || '二号';

    const snippets = {
        text: {
            heading: `【${headingLabel}标题】${headingText}\n`.replace(/\\n/g, '\n'),
            bold: `【加粗】${text || '加粗文字'}【/加粗】`,
            italic: `【斜体】${text || '斜体文字'}【/斜体】`,
            quote: `【引用】${text || '引用内容'}\n`.replace(/\\n/g, '\n'),
            ul: `【列表】${text || '列表项'}\n`.replace(/\\n/g, '\n'),
            ol: `【编号】${text || '列表项'}\n`.replace(/\\n/g, '\n'),
            link: `【链接：${text || '链接文字'}】https://example.com`,
            image: `【图片：${text || '图片描述'}】https://example.com/image.jpg`,
            code: `\n【代码】\n${text || '代码内容'}\n【/代码】\n`.replace(/\\n/g, '\n'),
            br: `\n`.replace(/\\n/g, '\n'),
            fontsize: `【大字】${text || '需要调整大小的文字'}【/大字】`,
        },
        markdown: {
            heading: `${'#'.repeat(headingLevel || 2)} ${headingText}\n\n`,
            bold: `**${text || '加粗文字'}**`,
            italic: `*${text || '斜体文字'}*`,
            quote: `> ${text || '引用内容'}\n\n`,
            ul: `- ${text || '列表项'}\n`,
            ol: `1. ${text || '列表项'}\n`,
            link: `[${text || '链接文字'}](https://example.com)`,
            image: `![${text || '图片描述'}](https://example.com/image.jpg)`,
            code: `\n\`\`\`\n${text || '代码内容'}\n\`\`\`\n`,
            br: `  \n`,
            fontsize: `[大字]${text || '需要调整大小的文字'}[/大字]`,
        },
        html: {
            heading: `<h${headingLevel || 2}>${headingText}</h${headingLevel || 2}>\n`,
            bold: `<strong>${text || '加粗文字'}</strong>`,
            italic: `<em>${text || '斜体文字'}</em>`,
            quote: `<blockquote>${text || '引用内容'}</blockquote>\n`,
            ul: `<ul>\n    <li>${text || '列表项'}</li>\n</ul>\n`,
            ol: `<ol>\n    <li>${text || '列表项'}</li>\n</ol>\n`,
            link: `<a href="https://example.com">${text || '链接文字'}</a>`,
            image: `<img src="https://example.com/image.jpg" alt="${text || '图片描述'}">\n`,
            code: `<pre><code>${text || '代码内容'}</code></pre>\n`,
            br: `<br>\n`,
            fontsize: `<span style="font-size: 20px;">${text || '需要调整大小的文字'}</span>`,
        },
    };

    return (snippets[mode] || snippets.markdown)[snippetType] || text;
}

function insertIntoTextarea(textarea, snippet) {
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    const before = textarea.value.slice(0, start);
    const after = textarea.value.slice(end);

    textarea.value = before + snippet + after;

    const nextPosition = start + snippet.length;
    textarea.focus();
    textarea.setSelectionRange(nextPosition, nextPosition);

    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function cleanEditorOutlineText(text) {
    return String(text || '')
        .replace(/<[^>]+>/g, '')
        .replace(/[#*_`>\[\]\(\)]/g, '')
        .replace(/^【(?:[一二三四五六]号)?标题】/, '')
        .trim();
}

function collectEditorOutline(content, mode) {
    const outline = [];

    if (mode === 'html') {
        const headingPattern = /<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi;
        let match;

        while ((match = headingPattern.exec(content)) !== null) {
            const text = cleanEditorOutlineText(match[2]);
            if (!text) continue;

            outline.push({
                type: 'content',
                level: Number.parseInt(match[1], 10),
                text,
                position: match.index,
            });
        }

        return outline;
    }

    let position = 0;
    content.split(/\n/).forEach((line) => {
        const markdownMatch = mode === 'markdown' ? line.match(/^(#{1,6})\s+(.+)$/) : null;
        const textMatch = mode === 'text' ? line.match(/^【(?:(一|二|三|四|五|六)号)?标题】(.+)$/) : null;
        let level = 2;
        let text = '';

        if (markdownMatch) {
            level = Math.min(markdownMatch[1].length, 6);
            text = markdownMatch[2];
        } else if (textMatch) {
            const labelMap = { 一: 1, 二: 2, 三: 3, 四: 4, 五: 5, 六: 6 };
            level = labelMap[textMatch[1]] || 2;
            text = textMatch[2];
        }

        text = cleanEditorOutlineText(text);
        if (text) {
            outline.push({
                type: 'content',
                level,
                text,
                position,
            });
        }

        position += line.length + 1;
    });

    return outline;
}

function renderEditorOutline(outlineElement, entries, textarea) {
    outlineElement.innerHTML = '';
    outlineElement.classList.toggle('is-empty', entries.length === 0);

    if (entries.length === 0) {
        const empty = document.createElement('button');
        empty.className = 'admin-outline-item admin-outline-empty';
        empty.type = 'button';
        empty.textContent = '暂无小标题';
        empty.addEventListener('click', () => textarea.focus());
        outlineElement.appendChild(empty);
        return;
    }

    entries.forEach((entry) => {
        const button = document.createElement('button');
        button.className = `admin-outline-item level-${entry.level}`;
        button.type = 'button';
        button.textContent = entry.text;

        button.addEventListener('click', () => {
            textarea.focus();
            textarea.setSelectionRange(entry.position, entry.position);
        });

        outlineElement.appendChild(button);
    });
}

function initAdminEditor() {
    const textarea = document.getElementById('content');
    const titleInput = document.getElementById('title');
    const toolbar = document.querySelector('.admin-editor-toolbar');
    const modeInputs = document.querySelectorAll('input[name="content_mode"]');
    const outlineElement = document.querySelector('[data-editor-outline]');
    const countElement = document.querySelector('[data-writing-count]');

    if (!textarea || !toolbar || modeInputs.length === 0) {
        return;
    }

    const updateCount = () => {
        if (!countElement) {
            return;
        }

        const titleLength = titleInput ? titleInput.value.trim().length : 0;
        const contentLength = textarea.value.replace(/\s/g, '').length;
        countElement.textContent = `共 ${titleLength + contentLength} 字`;
    };

    const updateOutline = () => {
        if (!outlineElement) {
            updateCount();
            return;
        }

        renderEditorOutline(
            outlineElement,
            collectEditorOutline(textarea.value, getEditorMode()),
            textarea
        );
        updateCount();
    };

    modeInputs.forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.admin-mode-tab').forEach((tab) => {
                tab.classList.toggle('active', tab.contains(input) && input.checked);
            });
            updateOutline();
        });
    });

    toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-insert]');
        if (!button) {
            return;
        }

        const type = button.getAttribute('data-insert');
        const selectedText = textarea.value.slice(textarea.selectionStart || 0, textarea.selectionEnd || 0);
        const snippet = editorSnippet(type, getEditorMode(), selectedText);

        insertIntoTextarea(textarea, snippet);
    });

    textarea.addEventListener('input', updateOutline);

    if (titleInput) {
        titleInput.addEventListener('input', updateOutline);
    }

    updateOutline();
}

function initWritingCollapses() {
    document.querySelectorAll('[data-writing-collapse]').forEach((section) => {
        const button = section.querySelector('[data-writing-collapse-toggle]');
        const body = section.querySelector('[data-writing-collapse-body]');

        if (!button || !body) {
            return;
        }

        button.addEventListener('click', () => {
            const isCollapsed = section.classList.toggle('is-collapsed');
            button.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            body.hidden = isCollapsed;
        });
    });
}

function initPostTagEditors() {
    document.querySelectorAll('[data-tag-editor]').forEach((editor) => {
        const hidden = editor.parentElement ? editor.parentElement.querySelector('[data-tags-hidden]') : null;
        const input = editor.querySelector('[data-tag-input]');
        const addButton = editor.querySelector('[data-tag-add]');
        const list = editor.querySelector('[data-tag-list]');
        const maxTags = Number.parseInt(editor.getAttribute('data-max-tags') || '3', 10);

        if (!hidden || !input || !addButton || !list) {
            return;
        }

        const readTags = () => Array.from(list.querySelectorAll('[data-tag-chip]'))
            .map((chip) => (chip.getAttribute('data-tag-chip') || '').trim())
            .filter(Boolean);

        const syncTags = () => {
            Array.from(list.querySelectorAll('[data-tag-chip]'))
                .slice(maxTags)
                .forEach((chip) => chip.remove());
            const tags = readTags().slice(0, maxTags);
            hidden.value = tags.join(',');
            const isFull = tags.length >= maxTags;
            addButton.disabled = isFull;
            input.disabled = isFull;
            input.placeholder = isFull ? `最多 ${maxTags} 个标签` : '输入标签';
        };

        const createChip = (tag) => {
            const chip = document.createElement('span');
            const text = document.createElement('span');
            const remove = document.createElement('button');

            chip.className = 'admin-tag-chip';
            chip.setAttribute('data-tag-chip', tag);
            text.textContent = tag;
            remove.type = 'button';
            remove.setAttribute('data-tag-remove', '');
            remove.setAttribute('aria-label', `删除 ${tag}`);
            remove.textContent = '×';

            chip.append(text, remove);
            return chip;
        };

        const addTag = () => {
            const tag = input.value.trim().replace(/[,，\s]+/g, ' ');
            if (!tag) {
                return;
            }

            if (readTags().length >= maxTags) {
                input.value = '';
                return;
            }

            const exists = readTags().some((item) => item.toLowerCase() === tag.toLowerCase());
            if (!exists) {
                list.appendChild(createChip(tag));
                syncTags();
            }

            input.value = '';
            input.focus();
        };

        addButton.addEventListener('click', addTag);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addTag();
            }
        });

        list.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-tag-remove]');
            if (!remove) {
                return;
            }

            const chip = remove.closest('[data-tag-chip]');
            if (chip) {
                chip.remove();
                syncTags();
            }
        });

        syncTags();
    });
}

function initGuestbookReplyForms() {
    const textareas = document.querySelectorAll('[data-reply-textarea]');

    textareas.forEach((textarea) => {
        const id = textarea.getAttribute('id');
        const counter = id
            ? document.querySelector(`[data-reply-counter-for="${CSS.escape(id)}"]`)
            : null;
        const maxLength = Number.parseInt(textarea.getAttribute('maxlength') || '1000', 10);

        const updateCounter = () => {
            const length = textarea.value.length;
            const isOverLimit = length > maxLength;

            if (counter) {
                counter.textContent = `${length}/${maxLength}`;
                counter.classList.toggle('is-over-limit', isOverLimit);
            }

            textarea.classList.toggle('is-over-limit', isOverLimit);
        };

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}


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

initAdminTheme();
initAdminHeader();
initAdminSidebar();
initAdminLoginLoading();
initAdminLoginSubmit();
initAdminEditor();
initWritingCollapses();
initPostTagEditors();
initGuestbookReplyForms();
initCategoryModalEditor();
initCategoryDeleteModal();
initAnnouncementDeleteModal();
initGuestbookConfirmModal();
initAdminModals();
initHeroSlideSettings();
initAdminToasts();
initAdminAsyncForms();
initAdminProfilePanels();
initAdminProfileAvatarPicker();
initAdminUpdateCheck();
initAdminSettingsForms();
initServerMetrics();
initServerCollapse();
initTrendChart();

// 后台全屏加载动画：只有刷新页面时播放——等字体加载完成→等所有字母完全显示→等页面就绪→淡出
// 点击侧栏导航跳转的新页面（navigate）由内联脚本加 boot-skip 类，直接跳过全屏动画
(function initAdminBoot() {
    const boot = document.querySelector('[data-admin-boot]');
    if (!boot) {
        return;
    }

    // 点击侧栏导航进入的页面：直接移除全屏遮罩，不播放动画
    if (document.documentElement.classList.contains('boot-skip')) {
        boot.remove();
        return;
    }

    const LETTER_ANIMATION_DURATION = 2600;
    const LETTER_STAGGER = 80;
    const LETTER_FILL_DELAY = 260;
    const LETTER_FULLY_VISIBLE_PROGRESS = 0.34;

    const letters = Array.from(boot.querySelectorAll('.admin-boot-letter'));
    const startTime = Date.now();

    const getLoadingRevealDuration = () => (
        Math.max(0, letters.length - 1) * LETTER_STAGGER
        + LETTER_FILL_DELAY
        + (LETTER_ANIMATION_DURATION * LETTER_FULLY_VISIBLE_PROGRESS)
    );

    const waitForAnimationFrame = () => new Promise((resolve) => {
        window.requestAnimationFrame(() => resolve());
    });

    const waitForLoadingRevealCompletion = () => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, getLoadingRevealDuration() - elapsed);
        return new Promise((resolve) => {
            window.setTimeout(resolve, remaining);
        });
    };

    // 等待页面真正加载完成（DOM 解析 + 资源就绪），全屏动画要盖住整个加载过程
    const waitForPageLoaded = () => new Promise((resolve) => {
        if (document.readyState === 'complete') {
            resolve();
            return;
        }
        window.addEventListener('load', resolve, { once: true });
    });

    const waitForUpdateCheck = () => {
        const updateReady = window.__zblogAdminUpdateReady;
        if (!updateReady || typeof updateReady.then !== 'function') {
            return Promise.resolve();
        }

        return updateReady.catch(() => {});
    };

    const dismiss = () => {
        boot.classList.add('is-leaving');
        window.setTimeout(() => {
            boot.classList.add('is-hidden');
            boot.remove();
        }, 920);
    };

    // 等待 MiSans 字体（动画用的 700 字重）真正下载完成
    const fontsReady = (document.fonts && typeof document.fonts.load === 'function')
        ? Promise.all([
            document.fonts.load('700 32px MiSans'),
            document.fonts.ready,
        ]).then(() => {})
        : (document.fonts && typeof document.fonts.ready === 'object'
            ? document.fonts.ready
            : Promise.resolve());

    fontsReady.then(() => Promise.resolve())
        .then(waitForAnimationFrame)
        .then(waitForAnimationFrame)
        .then(waitForLoadingRevealCompletion)
        .then(waitForPageLoaded)
        .then(waitForUpdateCheck)
        .then(dismiss)
        .catch(dismiss);
})();

// 服务器子区块折叠/展开（系统负载、详细信息各自独立）
function initServerCollapse() {
    document.querySelectorAll('[data-collapse]').forEach((block) => {
        const toggle = block.querySelector('[data-collapse-toggle]');
        if (!toggle) return;
        // 初始状态：info 默认收起，load 默认展开
        const initial = block.getAttribute('data-collapse');
        const startCollapsed = initial === 'info';
        block.setAttribute('data-collapsed', startCollapsed ? 'true' : 'false');
        toggle.setAttribute('aria-expanded', startCollapsed ? 'false' : 'true');

        toggle.addEventListener('click', () => {
            const collapsed = block.getAttribute('data-collapsed') === 'true';
            const next = !collapsed;
            block.setAttribute('data-collapsed', next ? 'true' : 'false');
            toggle.setAttribute('aria-expanded', next ? 'false' : 'true');
        });
    });
}

// 每 1 秒轮询服务器指标，更新圆环 + 负载折线图
function initServerMetrics() {
    const card = document.querySelector('[data-server-card]');
    if (!card) return;

    const ringEls = {
        cpu: { ring: card.querySelector('[data-ring="cpu"]') },
        memory: { ring: card.querySelector('[data-ring="memory"]') },
        disk: { ring: card.querySelector('[data-ring="disk"]') },
    };

    const loadNow = card.querySelector('[data-load-now]');
    const loadPath = card.querySelector('[data-load-path]');
    const loadM1 = card.querySelector('[data-load-m1]');
    const loadM5 = card.querySelector('[data-load-m5]');
    const loadM15 = card.querySelector('[data-load-m15]');

    const loadHistory = [];
    const MAX_POINTS = 30;

    // 颜色分级：< 50 深绿 | 50-69 浅绿 | 70-79 黄 | 80-89 橙 | >= 90 红
    const colorFor = (p) => {
        if (p === null || p === undefined) return 'var(--admin-text-muted)';
        if (p >= 90) return '#dc2626';
        if (p >= 80) return '#ea580c';
        if (p >= 70) return '#ca8a04';
        if (p >= 50) return '#65a30d';
        return '#15803d';
    };

    const updateRing = (key, percent, sub) => {
        const r = ringEls[key];
        if (!r || !r.ring) return;
        const prog = r.ring.querySelector('.dash-ring-prog');
        const pctEl = r.ring.querySelector('[data-ring-pct]');
        const subEl = r.ring.querySelector('[data-ring-sub]');
        const p = percent !== null && percent !== undefined ? Math.max(0, Math.min(100, percent)) : 0;
        const circ = parseFloat(prog.getAttribute('data-circumference')) || (2 * Math.PI * 42);
        prog.setAttribute('stroke-dashoffset', String(circ * (1 - p / 100)));
        prog.setAttribute('stroke', colorFor(percent));
        if (pctEl) pctEl.textContent = (percent !== null && percent !== undefined) ? percent + '%' : '—';
        if (subEl && sub !== undefined) subEl.textContent = sub;
    };

    const drawLoad = (load) => {
        if (!load || !loadPath) return;
        loadHistory.push(load.m1);
        if (loadHistory.length > MAX_POINTS) loadHistory.shift();

        const maxVal = Math.max(1, ...loadHistory);
        const w = 300, h = 80, pad = 6;
        const stepX = loadHistory.length > 1 ? (w - pad * 2) / (MAX_POINTS - 1) : 0;
        const points = [];
        loadHistory.forEach((v, i) => {
            const x = pad + i * stepX;
            const y = h - pad - (v / maxVal) * (h - pad * 2);
            points.push((i === 0 ? 'M' : 'L') + x.toFixed(1) + ',' + y.toFixed(1));
        });
        loadPath.setAttribute('d', points.join(' '));

        if (loadNow) loadNow.textContent = String(load.m1);
        if (loadM1) loadM1.textContent = '1m: ' + load.m1;
        if (loadM5) loadM5.textContent = '5m: ' + load.m5;
        if (loadM15) loadM15.textContent = '15m: ' + load.m15;
    };

    let metricsRequestRunning = false;
    const fetchOnce = () => {
        if (metricsRequestRunning) return;
        metricsRequestRunning = true;

        fetch('/admin/api/server-metrics', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.ok ? res.json() : Promise.reject())
            .then((data) => {
                updateRing('cpu', data.cpu.percent, (data.cpu.cores || 0) + ' 核');
                updateRing('memory', data.memory.percent, String(data.memory.used || '0') + ' / ' + String(data.memory.total || '0'));
                updateRing('disk', data.disk.percent, String(data.disk.used || '0') + ' / ' + String(data.disk.total || '0'));
                if (data.load) drawLoad(data.load);
            })
            .catch(() => {})
            .finally(() => {
                metricsRequestRunning = false;
            });
    };

    // 首次延迟 1s 再开始，避免和页面加载争抢
    setTimeout(fetchOnce, 1000);
    setInterval(fetchOnce, 1000);
}

// 近 7 天趋势折线图：鼠标悬停/点击显示当天数量
function initTrendChart() {
    const body = document.querySelector('[data-trend-chart]');
    if (!body) return;
    const svg = body.querySelector('.dash-chart');
    const tooltip = body.querySelector('[data-trend-tooltip]');
    if (!svg || !tooltip) return;

    let data = [];
    try {
        data = JSON.parse(body.getAttribute('data-trend-json') || '[]');
    } catch (e) { return; }
    if (!data.length) return;

    const series = [
        { key: 'posts', color: '#111827', label: '文章' },
        { key: 'comments', color: '#6366f1', label: '评论' },
        { key: 'likes', color: '#ec4899', label: '点赞' },
    ];

    // 预渲染每个数据点的圆点（透明，hover 时高亮）
    const vb = svg.viewBox.baseVal;
    const padL = 44, padR = 16, padT = 20, padB = 34;
    const plotW = vb.width - padL - padR;
    const plotH = vb.height - padT - padB;
    const stepX = data.length > 1 ? plotW / (data.length - 1) : plotW;
    const maxV = Math.max(4, Math.ceil(Math.max(...data.flatMap(d => [d.posts, d.comments, d.likes])) * 1.15));
    const yOf = (v) => padT + plotH - (v / maxV) * plotH;

    const pointsLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    const dots = [];
    series.forEach((s) => {
        data.forEach((d, i) => {
            const x = padL + i * stepX;
            const y = yOf(d[s.key]);
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', x.toFixed(1));
            c.setAttribute('cy', y.toFixed(1));
            c.setAttribute('r', '3.5');
            c.setAttribute('fill', s.color);
            c.setAttribute('stroke', 'var(--admin-surface)');
            c.setAttribute('stroke-width', '1.5');
            c.setAttribute('opacity', '0');
            c.style.transition = 'opacity 0.15s ease, r 0.15s ease';
            pointsLayer.appendChild(c);
            dots.push({ el: c, si: series.indexOf(s), di: i });
        });
    });
    svg.appendChild(pointsLayer);

    // 高亮指示线
    const guide = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    guide.setAttribute('x1', '0'); guide.setAttribute('y1', padT);
    guide.setAttribute('x2', '0'); guide.setAttribute('y2', padT + plotH);
    guide.setAttribute('stroke', 'currentColor');
    guide.setAttribute('stroke-width', '1');
    guide.setAttribute('stroke-dasharray', '3 3');
    guide.setAttribute('opacity', '0');
    svg.appendChild(guide);

    let activeIdx = -1;

    const showAt = (idx) => {
        if (idx < 0 || idx >= data.length) return;
        activeIdx = idx;
        const x = padL + idx * stepX;
        guide.setAttribute('x1', x.toFixed(1));
        guide.setAttribute('x2', x.toFixed(1));
        guide.setAttribute('opacity', '0.35');

        dots.forEach((dt) => {
            if (dt.di === idx) {
                dt.el.setAttribute('opacity', '1');
                dt.el.setAttribute('r', '4.5');
            } else {
                dt.el.setAttribute('opacity', '0');
                dt.el.setAttribute('r', '3.5');
            }
        });

        const d = data[idx];
        let rows = '';
        series.forEach((s) => {
            rows += '<div class="dash-chart-tooltip-row">'
                + '<span class="dash-chart-tooltip-label"><i style="background:' + s.color + '"></i>' + s.label + '</span>'
                + '<span class="dash-chart-tooltip-val">' + d[s.key] + '</span>'
                + '</div>';
        });
        tooltip.innerHTML = '<div class="dash-chart-tooltip-title">' + d.label + '</div>' + rows;
        tooltip.hidden = false;

        // 定位 tooltip（基于 SVG 显示尺寸换算）
        const rect = svg.getBoundingClientRect();
        const scaleX = rect.width / vb.width;
        const scaleY = rect.height / vb.height;
        const tipX = x * scaleX;
        const tipY = padT * scaleY;

        tooltip.style.left = tipX + 'px';
        tooltip.style.top = tipY + 'px';
        tooltip.hidden = false;

        // 边缘检测：tooltip 显示后根据实际宽度判断是否超出容器，加 class 调整偏移
        const bodyRect = body.getBoundingClientRect();
        const tipRect = tooltip.getBoundingClientRect();
        const overflowsRight = (tipRect.right > bodyRect.right - 4);
        const overflowsLeft = (tipRect.left < bodyRect.left + 4);
        tooltip.classList.toggle('is-edge-right', overflowsRight);
        tooltip.classList.toggle('is-edge-left', overflowsLeft);
    };

    const hide = () => {
        activeIdx = -1;
        guide.setAttribute('opacity', '0');
        dots.forEach((dt) => { dt.el.setAttribute('opacity', '0'); });
        tooltip.hidden = true;
    };

    const idxFromEvent = (e) => {
        const rect = svg.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width * vb.width;
        // 找最近的点
        let best = 0, bestDist = Infinity;
        for (let i = 0; i < data.length; i++) {
            const x = padL + i * stepX;
            const dist = Math.abs(px - x);
            if (dist < bestDist) { bestDist = dist; best = i; }
        }
        return best;
    };

    svg.addEventListener('mousemove', (e) => showAt(idxFromEvent(e)));
    svg.addEventListener('mouseleave', hide);
    svg.addEventListener('click', (e) => showAt(idxFromEvent(e)));
}




