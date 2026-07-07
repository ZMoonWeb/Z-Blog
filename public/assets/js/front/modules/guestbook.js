(() => {
    const stage = document.querySelector('[data-guestbook-barrage-stage]');

    if (!stage) {
        return;
    }

    const items = Array.from(stage.querySelectorAll('[data-guestbook-barrage]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const syncBarrageLayout = () => {
        if (reduceMotion || items.length === 0) {
            return;
        }

        const stageHeight = Math.max(stage.clientHeight, 360);
        const laneGap = 70;
        const laneCount = Math.max(1, Math.floor((stageHeight - 26) / laneGap));

        items.forEach((item, index) => {
            const lane = index % laneCount;
            const top = 18 + lane * laneGap + (index % 2) * 6;
            const duration = 26;
            const delay = -1 * (index * 3.8);

            item.style.setProperty('--barrage-top', `${top}px`);
            item.style.setProperty('--barrage-duration', `${duration}s`);
            item.style.setProperty('--barrage-delay', `${delay}s`);
        });
    };

    syncBarrageLayout();
    window.addEventListener('resize', syncBarrageLayout, { passive: true });
})();

(() => {
    const form = document.querySelector('.guestbook-compose-page .guestbook-form');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...frontCsrfHeaders(),
                },
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || result.success === false) {
                window.showToast(result.message || '留言发送失败，请稍后再试', 3000, 'error');
                return;
            }

            const textarea = form.querySelector('textarea[name="content"]');
            if (textarea) {
                textarea.value = '';
            }

            window.showToast(result.message || '留言已发布，3 秒后返回留言板。', 3000, 'success');
            window.setTimeout(() => {
                window.location.href = result.redirect || '/guestbook';
            }, 3000);
        } catch (error) {
            window.showToast('留言发送失败，请稍后再试', 3000, 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
})();
