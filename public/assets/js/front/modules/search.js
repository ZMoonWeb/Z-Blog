(() => {
    const search = document.querySelector('[data-site-search]');

    if (!search) {
        return;
    }

    const toggleButton = search.querySelector('[data-site-search-toggle]');
    const panel = search.querySelector('[data-site-search-panel]');
    const input = search.querySelector('[data-site-search-input]');
    const closeButton = search.querySelector('[data-site-search-close]');
    const form = search.querySelector('[data-site-search-form]');
    const header = search.closest('.site-header');
    const headerInner = header ? header.querySelector('.site-header-inner') : null;
    const reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 480px)') : null;

    if (!toggleButton || !panel || !input || !closeButton || !form) {
        return;
    }

    const classes = {
        open: 'is-open',
        closing: 'is-closing',
        mobile: 'is-mobile-search',
        mobileHeader: 'is-mobile-searching',
    };

    let activeAnimation = null;
    let closeTimer = null;
    let openFrame = null;
    let sequence = 0;
    let lastFocusTarget = null;

    const isReducedMotion = () => Boolean(reduceMotionQuery && reduceMotionQuery.matches);
    const isMobile = () => Boolean(mobileQuery && mobileQuery.matches);

    const getSearchMode = () => (isMobile() ? 'mobile' : 'desktop');

    const cancelAnimation = () => {
        if (activeAnimation) {
            activeAnimation.cancel();
            activeAnimation = null;
        }
    };

    const clearCloseTimer = () => {
        window.clearTimeout(closeTimer);
        closeTimer = null;
    };

    const cancelOpenFrame = () => {
        if (openFrame) {
            window.cancelAnimationFrame(openFrame);
            openFrame = null;
        }
    };

    const isOpen = () => !panel.hidden || search.classList.contains(classes.open);

    const setToggleState = (open) => {
        toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggleButton.setAttribute('aria-label', open ? '搜索框已展开' : '打开搜索');
    };

    const focusElement = (element) => {
        if (!element || typeof element.focus !== 'function') {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    };

    const runPanelAnimation = (keyframes, options) => {
        cancelAnimation();

        if (isReducedMotion() || typeof panel.animate !== 'function') {
            panel.style.opacity = keyframes[keyframes.length - 1].opacity || '';
            panel.style.transform = keyframes[keyframes.length - 1].transform || '';
            return Promise.resolve();
        }

        const animation = panel.animate(keyframes, {
            duration: 240,
            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
            fill: 'both',
            ...options,
        });
        activeAnimation = animation;

        return animation.finished.catch(() => {}).finally(() => {
            if (activeAnimation === animation) {
                activeAnimation = null;
            }
        });
    };

    const resetInlinePanelPosition = () => {
        panel.style.removeProperty('--mobile-search-left');
        panel.style.removeProperty('--mobile-search-top');
        panel.style.removeProperty('--mobile-search-width');
        panel.style.opacity = '';
        panel.style.transform = '';
    };

    const syncMobilePanelPosition = () => {
        const shell = headerInner || header || search;
        const shellRect = shell.getBoundingClientRect();
        const visualViewport = window.visualViewport || null;
        const viewportWidth = visualViewport ? visualViewport.width : window.innerWidth;
        const viewportLeft = visualViewport ? visualViewport.offsetLeft : 0;
        const viewportTop = visualViewport ? visualViewport.offsetTop : 0;
        const sideGap = Math.max(12, Math.min(18, Math.round(viewportWidth * 0.035)));
        const formHeight = 40;
        const top = Math.max(8, Math.round(viewportTop + shellRect.top + (shellRect.height - formHeight) / 2));
        const left = Math.round(viewportLeft + sideGap);
        const width = Math.max(220, Math.round(viewportWidth - sideGap * 2));

        panel.style.setProperty('--mobile-search-left', `${left}px`);
        panel.style.setProperty('--mobile-search-top', `${top}px`);
        panel.style.setProperty('--mobile-search-width', `${width}px`);
    };

    const prepareOpen = (mode) => {
        clearCloseTimer();
        cancelAnimation();
        panel.hidden = false;
        search.classList.remove(classes.closing);
        search.classList.add(classes.open);
        setToggleState(true);

        if (mode === 'mobile') {
            search.classList.add(classes.mobile);
            header?.classList.add(classes.mobileHeader);
            syncMobilePanelPosition();
            return;
        }

        search.classList.remove(classes.mobile);
        header?.classList.remove(classes.mobileHeader);
        resetInlinePanelPosition();
    };

    const finishClose = (returnFocus) => {
        panel.hidden = true;
        search.classList.remove(classes.open, classes.closing, classes.mobile);
        header?.classList.remove(classes.mobileHeader);
        resetInlinePanelPosition();

        if (returnFocus) {
            focusElement(lastFocusTarget || toggleButton);
        }

        lastFocusTarget = null;
    };

    const openDesktopSearch = () => {
        clearCloseTimer();
        cancelOpenFrame();
        cancelAnimation();

        if (search.classList.contains(classes.open) && !panel.hidden) {
            return;
        }

        sequence += 1;
        panel.hidden = false;
        resetInlinePanelPosition();
        search.classList.remove(classes.closing, classes.mobile);
        header?.classList.remove('is-search-closing');
        header?.classList.remove(classes.mobileHeader);
        header?.classList.add('is-search-active');
        setToggleState(true);

        panel.getBoundingClientRect();
        search.classList.add(classes.open);

        // 电脑端：仅展开搜索面板，不自动聚焦输入框，由用户点击输入框后再输入
        openFrame = window.requestAnimationFrame(() => {
            openFrame = null;
        });
    };

    const closeDesktopSearch = ({ returnFocus = false } = {}) => {
        clearCloseTimer();
        cancelOpenFrame();
        cancelAnimation();

        if (panel.hidden && !search.classList.contains(classes.open)) {
            return;
        }

        sequence += 1;
        search.classList.remove(classes.open);
        search.classList.remove(classes.closing);
        header?.classList.remove('is-search-active');
        header?.classList.remove('is-search-closing');
        setToggleState(false);
        panel.hidden = true;

        if (returnFocus) {
            focusElement(lastFocusTarget || toggleButton);
        }

        lastFocusTarget = null;
    };

    const openMobileSearch = async () => {
        const token = ++sequence;
        lastFocusTarget = document.activeElement instanceof HTMLElement ? document.activeElement : toggleButton;
        prepareOpen('mobile');

        await runPanelAnimation([
            { opacity: 0, transform: 'translate3d(0, 0, 0) scale(0.98)' },
            { opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)' },
        ], { duration: 180, easing: 'ease-out' });

        if (token !== sequence) {
            return;
        }

        panel.style.opacity = '';
        panel.style.transform = '';
    };

    const closeMobileSearch = (token, returnFocus) => {
        setToggleState(false);

        if (token === sequence) {
            finishClose(returnFocus);
        }
    };

    const openSearch = () => {
        if (getSearchMode() === 'mobile') {
            if (search.classList.contains(classes.open)) {
                return;
            }

            openMobileSearch();
            return;
        }

        openDesktopSearch();
    };

    const closeSearch = ({ returnFocus = false } = {}) => {
        if (!isOpen()) {
            return;
        }

        if (search.classList.contains(classes.mobile)) {
            clearCloseTimer();
            cancelAnimation();
            const token = ++sequence;
            closeMobileSearch(token, returnFocus);
            return;
        }

        closeDesktopSearch({ returnFocus });
    };

    toggleButton.addEventListener('click', () => {
        if (!search.classList.contains(classes.open)) {
            openSearch();
        }
    });

    closeButton.addEventListener('click', () => {
        closeSearch({ returnFocus: true });
    });

    form.addEventListener('submit', (event) => {
        const keyword = input.value.replace(/\s+/g, ' ').trim().slice(0, 80);

        if (keyword === '') {
            event.preventDefault();
            input.value = '';
            input.focus();
            return;
        }

        input.value = keyword;
    });

    window.addEventListener('resize', () => {
        if (search.classList.contains(classes.mobile) && getSearchMode() === 'mobile') {
            syncMobilePanelPosition();
        }
    }, { passive: true });

    if (window.visualViewport) {
        const syncVisualViewport = () => {
            if (search.classList.contains(classes.mobile)) {
                syncMobilePanelPosition();
            }
        };

        window.visualViewport.addEventListener('resize', syncVisualViewport, { passive: true });
        window.visualViewport.addEventListener('scroll', syncVisualViewport, { passive: true });
    }

})();
