(() => {
    const likeButton = document.querySelector('.pd-like-btn[data-post-slug]');

    if (!likeButton) {
        return;
    }

    const form = likeButton.closest('form');

    if (!form) {
        return;
    }

    let isSubmitting = false;

    const updateButtonState = (liked, count) => {
        likeButton.classList.toggle('is-liked', liked);
        const countSpan = likeButton.querySelector('span');
        if (countSpan) {
            countSpan.textContent = new Intl.NumberFormat('zh-CN').format(count);
        }

        // 点赞时触发跳跃动画
        if (liked) {
            likeButton.classList.remove('is-animating');
            // 强制重绘以重新触发动画
            void likeButton.offsetWidth;
            likeButton.classList.add('is-animating');

            // 动画结束后移除类
            setTimeout(() => {
                likeButton.classList.remove('is-animating');
            }, 600);
        }
    };

    const handleLike = async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        isSubmitting = true;
        likeButton.disabled = true;

        const postSlug = likeButton.getAttribute('data-post-slug');
        const formData = new FormData(form);

        let fingerprint = '';
        try {
            fingerprint = await zmoonBrowserFingerprint();
        } catch (error) {
            fingerprint = '';
        }
        if (fingerprint) {
            formData.append('browser_fingerprint', fingerprint);
        }

        try {
            const response = await fetch(`/post/${encodeURIComponent(postSlug)}/like`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...frontCsrfHeaders(),
                    ...(fingerprint ? { 'X-Browser-Fingerprint': fingerprint } : {}),
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (typeof data.liked === 'boolean' && typeof data.likeCount === 'number') {
                updateButtonState(data.liked, data.likeCount);
            }
        } catch (error) {
            console.error('点赞失败:', error);
            form.submit();
        } finally {
            isSubmitting = false;
            likeButton.disabled = false;
        }
    };

    form.addEventListener('submit', handleLike);
})();

// 首页文章卡片点赞：乐观更新，点击即时变红 +1 并触发心形动效，再次点击取消（无动效），
// 无加载动画；按浏览器指纹记录与判重
(() => {
    const buttons = document.querySelectorAll('[data-like-toggle]');
    if (!buttons.length) {
        return;
    }

    const formatter = new Intl.NumberFormat('zh-CN');
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

    buttons.forEach((button) => {
        const form = button.closest('form');
        if (!form) {
            return;
        }

        const countSpan = button.querySelector('[data-like-count]');
        const heart = button.querySelector('.post-like-heart');
        const baseTitle = (button.getAttribute('aria-label') || '点赞').replace(/^(取消点赞|点赞)[：:]?\s*/, '').trim();

        let liked = button.classList.contains('is-liked');
        let count = countSpan ? parseInt(countSpan.textContent.replace(/[^0-9]/g, ''), 10) || 0 : 0;
        let serverLiked = liked;
        let serverCount = count;
        let inflight = false;
        let reconcileQueued = false;

        const render = () => {
            button.classList.toggle('is-liked', liked);
            button.setAttribute('aria-pressed', liked ? 'true' : 'false');
            button.setAttribute('aria-label', (liked ? '取消点赞' : '点赞') + (baseTitle ? '：' + baseTitle : ''));
            if (countSpan) {
                countSpan.textContent = formatter.format(count);
            }
        };

        const popHeart = () => {
            if (!heart || (reduceMotion && reduceMotion.matches)) {
                return;
            }
            heart.classList.remove('is-popping');
            void heart.offsetWidth;
            heart.classList.add('is-popping');
            heart.addEventListener('animationend', () => {
                heart.classList.remove('is-popping');
            }, { once: true });
        };

        const sendToggle = async () => {
            const formData = new FormData(form);
            let fingerprint = '';
            try {
                fingerprint = await zmoonBrowserFingerprint();
            } catch (error) {
                fingerprint = '';
            }
            if (fingerprint) {
                formData.append('browser_fingerprint', fingerprint);
            }
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...frontCsrfHeaders(),
                    ...(fingerprint ? { 'X-Browser-Fingerprint': fingerprint } : {}),
                },
                body: formData,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        };

        const reconcile = () => {
            if (inflight) {
                reconcileQueued = true;
                return;
            }
            if (liked === serverLiked) {
                return;
            }
            inflight = true;
            sendToggle()
                .then((data) => {
                    if (typeof data.liked === 'boolean') {
                        serverLiked = data.liked;
                    }
                    if (typeof data.likeCount === 'number') {
                        serverCount = data.likeCount;
                        if (serverLiked === liked) {
                            count = serverCount;
                        }
                    }
                    render();
                })
                .catch((error) => {
                    liked = serverLiked;
                    count = serverCount;
                    render();
                    console.error('点赞失败:', error);
                })
                .finally(() => {
                    inflight = false;
                    if (reconcileQueued) {
                        reconcileQueued = false;
                        reconcile();
                    }
                });
        };

        const handleSubmit = (event) => {
            event.preventDefault();
            liked = !liked;
            count += liked ? 1 : -1;
            render();
            if (liked) {
                popHeart();
            }
            reconcile();
        };

        form.addEventListener('submit', handleSubmit);
    });
})();
