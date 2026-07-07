(() => {
    const commentButton = document.querySelector('[data-toggle-comment-form]');
    const commentFormWrapper = document.getElementById('comment-form-wrapper');
    const cancelButton = document.querySelector('[data-cancel-comment]');
    const commentForm = document.querySelector('[data-comment-form]');
    const sectionCount = document.querySelector('[data-comment-section-count]');
    const actionCount = document.querySelector('[data-comment-action-count]');
    const commentsSection = document.querySelector('.pd-comments-section');

    if (!commentButton || !commentFormWrapper) {
        return;
    }

    const openCommentForm = () => {
        commentFormWrapper.style.display = 'block';
        commentFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        const contentInput = commentFormWrapper.querySelector('textarea[name="content"]');
        if (contentInput) {
            setTimeout(() => {
                contentInput.focus();
            }, 180);
        }
    };

    const closeCommentForm = () => {
        commentFormWrapper.style.display = 'none';
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const showCommentToast = (message, type = 'success') => {
        if (typeof window.showToast === 'function') {
            window.showToast(message, 3000, type);
        }
    };

    const renderComment = (comment) => {
        const card = document.createElement('div');
        card.className = 'pd-comment-card';
        card.innerHTML = `
            <div class="pd-comment-header">
                <span class="pd-comment-name">${escapeHtml(comment.author_name || '匿名用户')}</span>
                <time class="pd-comment-time">${escapeHtml(comment.created_at || '')}</time>
            </div>
            <p class="pd-comment-content">${escapeHtml(comment.content || '').replace(/\n/g, '<br>')}</p>
        `;

        return card;
    };

    const ensureCommentsList = () => {
        let list = document.querySelector('[data-comments-list]');
        if (list || !commentsSection) {
            return list;
        }

        list = document.createElement('div');
        list.className = 'pd-comments-list';
        list.setAttribute('data-comments-list', '');
        const empty = document.querySelector('[data-comments-empty]');

        if (empty) {
            empty.insertAdjacentElement('beforebegin', list);
        } else {
            commentsSection.appendChild(list);
        }

        return list;
    };

    const updateCommentCount = (count) => {
        const value = Number.parseInt(count, 10);
        if (!Number.isFinite(value)) {
            return;
        }

        if (sectionCount) {
            sectionCount.textContent = String(value);
        }

        if (actionCount) {
            actionCount.textContent = new Intl.NumberFormat().format(value);
        }
    };

    commentButton.addEventListener('click', openCommentForm);

    if (cancelButton) {
        cancelButton.addEventListener('click', closeCommentForm);
    }

    if (commentForm) {
        commentForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = commentForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const response = await fetch(commentForm.action, {
                    method: 'POST',
                    body: new FormData(commentForm),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...frontCsrfHeaders(),
                    },
                });
                const result = await response.json().catch(() => ({}));

                if (!response.ok || result.success === false) {
                    showCommentToast(result.message || '评论发送失败', 'error');
                    return;
                }

                const textarea = commentForm.querySelector('textarea[name="content"]');
                const list = ensureCommentsList();
                if (list && result.comment) {
                    list.prepend(renderComment(result.comment));
                }

                const empty = document.querySelector('[data-comments-empty]');
                if (empty) {
                    empty.hidden = true;
                }

                updateCommentCount(result.commentCount);
                if (textarea) {
                    textarea.value = '';
                }
                showCommentToast(result.message || '评论发送成功', 'success');
            } catch (error) {
                showCommentToast('评论发送失败，请稍后再试', 'error');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    }

    if (commentFormWrapper.querySelector('.pd-alert-error, .pd-alert-success')) {
        openCommentForm();
    }
})();
