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

initAdminEditor();
initWritingCollapses();
initPostTagEditors();
initGuestbookReplyForms();
