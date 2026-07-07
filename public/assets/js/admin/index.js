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
