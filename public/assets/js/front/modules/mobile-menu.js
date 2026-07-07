(() => {
    const openButton = document.querySelector('[data-mobile-menu-open]');
    const closeButton = document.querySelector('[data-mobile-menu-close]');
    const drawer = document.querySelector('[data-mobile-menu-drawer]');
    const mask = document.querySelector('[data-mobile-menu-mask]');
    const reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    if (!openButton || !closeButton || !drawer || !mask) {
        return;
    }

    let closeTimer = null;
    let openFrame = null;

    const isReducedMotion = () => Boolean(reduceMotionQuery && reduceMotionQuery.matches);

    const cancelOpenFrame = () => {
        if (openFrame) {
            window.cancelAnimationFrame(openFrame);
            openFrame = null;
        }
    };

    const setOpen = (open) => {
        window.clearTimeout(closeTimer);
        cancelOpenFrame();

        if (open) {
            mask.hidden = false;
            drawer.setAttribute('aria-hidden', 'false');
            openButton.setAttribute('aria-expanded', 'true');
            document.body.classList.add('is-menu-open');

            openFrame = window.requestAnimationFrame(() => {
                openFrame = null;
                mask.classList.add('is-open');
                drawer.classList.add('is-open');
            });
            return;
        }

        drawer.classList.remove('is-open');
        mask.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        openButton.setAttribute('aria-expanded', 'false');

        closeTimer = window.setTimeout(() => {
            mask.hidden = true;
            document.body.classList.remove('is-menu-open');
        }, isReducedMotion() ? 0 : 300);
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton.addEventListener('click', () => setOpen(false));
    mask.addEventListener('click', () => setOpen(false));

    drawer.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();
