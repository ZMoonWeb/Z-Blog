const markFrontJsReady = () => {
    document.documentElement.classList.add('js-ready');
};

(() => {
    const storageKey = 'zblog-front-theme';
    const root = document.documentElement;
    const toggleButton = document.querySelector('[data-theme-toggle]');
    const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    const reduceMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    const getStoredTheme = () => {
        try {
            const value = window.localStorage.getItem(storageKey);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (error) {
            return null;
        }
    };

    const setStoredTheme = (theme) => {
        try {
            window.localStorage.setItem(storageKey, theme);
        } catch (error) {
            // Ignore storage errors so the button still works for this visit.
        }
    };

    const getSystemTheme = () => (media && media.matches ? 'dark' : 'light');

    const updateToggleButton = (theme) => {
        if (!toggleButton) {
            return;
        }

        const label = theme === 'dark' ? '切换为浅色模式' : '切换为暗色模式';
        toggleButton.setAttribute('aria-label', label);
        toggleButton.setAttribute('title', label);
        toggleButton.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    };

    const applyTheme = (theme, source) => {
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-source', source);
        root.style.colorScheme = theme === 'light' ? 'only light' : 'dark';
        updateToggleButton(theme);
    };

    const playThemeRipple = (theme, origin) => {
        if (!origin || (reduceMotion && reduceMotion.matches)) {
            applyTheme(theme, 'manual');
            return;
        }

        const rect = origin.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const maxX = Math.max(x, window.innerWidth - x);
        const maxY = Math.max(y, window.innerHeight - y);
        const radius = Math.ceil(Math.hypot(maxX, maxY));
        const clipPath = [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`];

        if (typeof document.startViewTransition === 'function') {
            const transition = document.startViewTransition(() => {
                applyTheme(theme, 'manual');
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

        window.requestAnimationFrame(() => {
            applyTheme(theme, 'manual');
        });
    };

    const syncTheme = () => {
        const storedTheme = getStoredTheme();
        applyTheme(storedTheme || getSystemTheme(), storedTheme ? 'manual' : 'system');
    };

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            setStoredTheme(nextTheme);
            playThemeRipple(nextTheme, toggleButton);
        });
    }

    if (media) {
        const syncSystemTheme = () => {
            if (!getStoredTheme()) {
                applyTheme(getSystemTheme(), 'system');
            }
        };

        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', syncSystemTheme);
        } else if (typeof media.addListener === 'function') {
            media.addListener(syncSystemTheme);
        }
    }

    syncTheme();
})();

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

const frontAssets = (() => {
    const waitForWindowLoad = () => {
        if (document.readyState === 'complete') {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            window.addEventListener('load', resolve, { once: true });
        });
    };

    const decodeImage = (image) => {
        if (typeof image.decode !== 'function' || !image.currentSrc) {
            return Promise.resolve();
        }

        return image.decode().catch(() => {});
    };

    const waitForImage = (image) => {
        image.loading = 'eager';

        if (image.complete) {
            return decodeImage(image);
        }

        return new Promise((resolve) => {
            const done = () => {
                resolve(decodeImage(image));
            };

            image.addEventListener('load', done, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    };

    const waitForImages = () => {
        const images = Array.from(document.images);

        if (images.length === 0) {
            return Promise.resolve();
        }

        return Promise.all(images.map(waitForImage));
    };

    const waitForFonts = () => {
        if (!document.fonts || !document.fonts.ready) {
            return Promise.resolve();
        }

        if (typeof document.fonts.load === 'function') {
            return Promise.all([
                document.fonts.load('400 1em MiSans'),
                document.fonts.load('700 1em MiSans'),
                document.fonts.ready,
            ]).catch(() => document.fonts.ready.catch(() => {}));
        }

        return document.fonts.ready;
    };

    const windowLoaded = waitForWindowLoad();
    const fontsReady = waitForFonts();
    const imagesReady = waitForImages()
        .then(() => windowLoaded)
        .then(waitForImages);

    return {
        fontsReady,
        ready: Promise.all([windowLoaded, imagesReady, fontsReady]),
    };
})();

const frontLetterLoader = (() => {
    const LETTER_ANIMATION_DURATION = 2600;
    const LETTER_STAGGER = 80;
    const LETTER_FILL_DELAY = 260;
    const LETTER_LOOP_GAP = 220;
    const LETTER_FULLY_VISIBLE_PROGRESS = 0.34;

    const now = () => (
        window.performance && typeof window.performance.now === 'function'
            ? window.performance.now()
            : Date.now()
    );

    const create = (root, letterSelector = '.welcome-letter') => {
        const letters = Array.from(root.querySelectorAll(letterSelector));
        let loopTimer = null;
        let cycleStartedAt = 0;
        let running = false;

        const getLoopDuration = () => (
            LETTER_ANIMATION_DURATION
            + LETTER_FILL_DELAY
            + Math.max(0, letters.length - 1) * LETTER_STAGGER
            + LETTER_LOOP_GAP
        );

        const getFillDuration = () => (
            Math.max(0, letters.length - 1) * LETTER_STAGGER
            + LETTER_FILL_DELAY
            + (LETTER_ANIMATION_DURATION * LETTER_FULLY_VISIBLE_PROGRESS)
        );

        const getFillHoldDuration = () => (
            Math.max(0, letters.length - 1) * LETTER_STAGGER
            + LETTER_FILL_DELAY
            + (LETTER_ANIMATION_DURATION * 0.68)
        );

        const restart = () => {
            if (!running) {
                return;
            }

            root.classList.remove('is-loading-cycle');
            root.classList.remove('is-loading-complete');
            void root.offsetWidth;
            root.classList.add('is-loading-cycle');
            cycleStartedAt = now();
            loopTimer = window.setTimeout(restart, getLoopDuration());
        };

        return {
            start() {
                this.stop();
                running = true;
                restart();
            },
            stop() {
                running = false;
                window.clearTimeout(loopTimer);
                loopTimer = null;
                root.classList.remove('is-loading-cycle', 'is-loading-complete');
            },
            complete() {
                running = false;
                window.clearTimeout(loopTimer);
                loopTimer = null;
                root.classList.remove('is-loading-cycle');
                root.classList.add('is-loading-complete');
            },
            waitForCurrentFill() {
                const loopDuration = getLoopDuration();
                const rawElapsed = cycleStartedAt > 0 ? now() - cycleStartedAt : 0;
                const elapsed = loopDuration > 0 ? rawElapsed % loopDuration : rawElapsed;
                let remaining = getFillDuration() - elapsed;

                if (remaining < 0 && elapsed <= getFillHoldDuration()) {
                    remaining = 0;
                } else if (remaining < 0) {
                    remaining = Math.max(0, loopDuration - elapsed + getFillDuration());
                }

                return new Promise((resolve) => {
                    window.setTimeout(resolve, remaining);
                });
            },
        };
    };

    return { create };
})();

let frontLoadingReady = Promise.resolve();
const WELCOME_SESSION_KEY = 'zblog-welcome-shown';

const hasWelcomeBeenShown = () => {
    try {
        return sessionStorage.getItem(WELCOME_SESSION_KEY) === '1';
    } catch (error) {
        return false;
    }
};

const markWelcomeShown = () => {
    try {
        sessionStorage.setItem(WELCOME_SESSION_KEY, '1');
    } catch (error) {
        // Ignore storage errors; the current click should still enter the site.
    }
};

(() => {
    const loading = document.querySelector('[data-front-loading]');

    if (!loading) {
        markFrontJsReady();
        return;
    }

    const loader = frontLetterLoader.create(loading);
    const welcome = document.querySelector('[data-welcome-screen]');
    const shouldHandOffToWelcome = Boolean(welcome && !hasWelcomeBeenShown());

    const hideLoading = () => {
        loader.complete();
        loading.classList.add('is-hidden');

        return new Promise((resolve) => {
            window.setTimeout(() => {
                loading.remove();
                document.body.classList.remove('is-front-loading');
                resolve();
            }, 360);
        });
    };

    const handOffToWelcome = () => {
        loader.complete();
        loading.classList.add('is-welcome-handoff');
    };

    document.body.classList.add('is-front-loading');
    markFrontJsReady();

    frontLoadingReady = frontAssets.fontsReady
        .then(() => {
            loader.start();
            return frontAssets.ready;
        })
        .then(() => loader.waitForCurrentFill())
        .then(() => (shouldHandOffToWelcome ? handOffToWelcome() : hideLoading()));
})();

(() => {
    const welcome = document.querySelector('[data-welcome-screen]');

    if (!welcome) {
        return;
    }

    let dismissed = false;
    let readyToEnter = false;
    const enterPrompt = welcome.querySelector('.welcome-enter');
    const reduceMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    const dismiss = (force = false) => {
        if (dismissed) {
            return;
        }

        if (!force && !readyToEnter) {
            return;
        }

        dismissed = true;

        if (!force) {
            markWelcomeShown();
        }

        const loading = document.querySelector('[data-front-loading]');

        document.body.classList.remove('is-welcome-open', 'is-front-loading');
        welcome.classList.remove('is-preparing', 'is-ready', 'is-loading-handoff');
        welcome.classList.add('is-leaving');

        if (loading) {
            loading.classList.add('is-hidden');
            window.setTimeout(() => {
                loading.remove();
            }, 1040);
        }

        window.setTimeout(() => {
            welcome.classList.add('is-hidden');
            welcome.remove();
        }, 1120);
    };

    if (hasWelcomeBeenShown()) {
        welcome.remove();
        return;
    }

    document.body.classList.add('is-welcome-open');
    welcome.classList.add('is-preparing');
    welcome.classList.remove('welcome-boot');

    frontLoadingReady.then(() => {
        let enterReadyTimer = null;
        const allowEnter = () => {
            if (dismissed || !welcome.classList.contains('is-ready')) {
                return;
            }

            readyToEnter = true;
        };

        welcome.classList.remove('is-preparing');
        if (document.querySelector('[data-front-loading].is-welcome-handoff')) {
            welcome.classList.add('is-loading-handoff');
        }
        welcome.classList.add('is-ready');

        if (reduceMotion && reduceMotion.matches) {
            allowEnter();
            return;
        }

        enterReadyTimer = window.setTimeout(allowEnter, 1600);

        if (enterPrompt) {
            enterPrompt.addEventListener('animationend', (event) => {
                if (
                    event.animationName
                    && event.animationName !== 'welcomeEnterIn'
                ) {
                    return;
                }

                window.clearTimeout(enterReadyTimer);
                allowEnter();
            }, { once: true });
        }
    });

    // 点击 anywhere 或键盘 Enter/Space 即可进入
    welcome.addEventListener('click', () => {
        dismiss();
    });
    welcome.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            dismiss();
        }
    });
})();

(() => {
    const siteHeader = document.querySelector('.site-header');

    if (!siteHeader) {
        return;
    }

    const syncHeader = () => {
        siteHeader.classList.toggle('is-scrolled', window.scrollY > 4);
    };

    syncHeader();
    window.addEventListener('scroll', syncHeader, { passive: true });
})();

(() => {
    const panels = Array.from(document.querySelectorAll('.guestbook-stats-panel'));

    if (panels.length === 0) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    panels.forEach((panel) => {
        const summary = panel.querySelector('summary');

        if (!summary) {
            return;
        }

        let animation = null;

        const finishAnimation = (open) => {
            panel.open = open;
            panel.style.height = '';
            panel.classList.remove('is-collapsing', 'is-expanding');
            animation = null;
        };

        const animatePanel = (open) => {
            if (reduceMotion || typeof panel.animate !== 'function') {
                panel.open = open;
                return;
            }

            if (animation) {
                animation.cancel();
            }

            const startHeight = `${panel.offsetHeight}px`;

            panel.classList.toggle('is-expanding', open);
            panel.classList.toggle('is-collapsing', !open);

            if (open) {
                panel.open = true;
            }

            const endHeight = open ? `${panel.scrollHeight}px` : `${summary.offsetHeight}px`;

            animation = panel.animate(
                { height: [startHeight, endHeight] },
                { duration: 240, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
            );

            panel.style.height = startHeight;

            animation.onfinish = () => finishAnimation(open);
            animation.oncancel = () => {
                animation = null;
            };
        };

        summary.addEventListener('click', (event) => {
            event.preventDefault();
            animatePanel(!panel.open);
        });
    });
})();

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

(() => {
    const hero = document.querySelector('[data-hero-carousel]');

    if (!hero) {
        return;
    }

    const track = hero.querySelector('[data-hero-track]');
    const prevButton = hero.querySelector('[data-hero-prev]');
    const nextButton = hero.querySelector('[data-hero-next]');
    const slides = Array.from(hero.querySelectorAll('.hero-slide'));

    if (!track || slides.length <= 1) {
        return;
    }

    let current = 0;
    let timer = null;
    let startX = 0;
    let currentX = 0;
    let isDragging = false;
    let dragMoved = false;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const getOffset = (index) => {
        let offset = index - current;

        if (offset > slides.length / 2) {
            offset -= slides.length;
        }

        if (offset < -slides.length / 2) {
            offset += slides.length;
        }

        return offset;
    };

    const render = (dragPercent = 0) => {
        slides.forEach((slide, index) => {
            const offset = getOffset(index) + dragPercent;
            slide.classList.toggle('is-active', offset === 0);
            slide.classList.toggle('is-animating', !track.classList.contains('is-dragging'));
            slide.style.transform = `translateX(${offset * 100}%) scale(1)`;
            slide.style.zIndex = String(Math.max(1, 10 - Math.abs(Math.round(offset))));
        });
    };

    const goTo = (index) => {
        current = (index + slides.length) % slides.length;
        track.classList.remove('is-dragging');
        render();
    };

    const stopAuto = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const startAuto = () => {
        if (reduceMotion) {
            return;
        }

        stopAuto();
        timer = window.setInterval(() => goTo(current + 1), 5000);
    };

    const getClientX = (event) => {
        if (event.touches && event.touches[0]) {
            return event.touches[0].clientX;
        }

        if (event.changedTouches && event.changedTouches[0]) {
            return event.changedTouches[0].clientX;
        }

        return event.clientX;
    };

    const startDrag = (event) => {
        isDragging = true;
        dragMoved = false;
        startX = getClientX(event);
        currentX = startX;
        stopAuto();
        track.classList.add('is-dragging');
    };

    const moveDrag = (event) => {
        if (!isDragging) {
            return;
        }

        currentX = getClientX(event);
        const delta = currentX - startX;
        const width = hero.clientWidth || 1;
        dragMoved = Math.abs(delta) > 6;
        render((delta / width) * 1);
    };

    const endDrag = () => {
        if (!isDragging) {
            return;
        }

        isDragging = false;
        const delta = currentX - startX;
        const threshold = Math.max(48, (hero.clientWidth || 0) * 0.14);

        if (delta > threshold) {
            goTo(current - 1);
        } else if (delta < -threshold) {
            goTo(current + 1);
        } else {
            goTo(current);
        }

        startAuto();
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            goTo(current - 1);
            startAuto();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            goTo(current + 1);
            startAuto();
        });
    }

    hero.addEventListener('mouseenter', stopAuto);
    hero.addEventListener('mouseleave', startAuto);
    hero.addEventListener('focusin', stopAuto);
    hero.addEventListener('focusout', startAuto);
    hero.addEventListener('touchstart', startDrag, { passive: true });
    hero.addEventListener('touchmove', moveDrag, { passive: true });
    hero.addEventListener('touchend', endDrag);
    hero.addEventListener('touchcancel', endDrag);
    hero.addEventListener('click', (event) => {
        if (!dragMoved) {
            return;
        }

        event.preventDefault();
        dragMoved = false;
    });

    render();
    startAuto();
})();

(() => {
    const panelRoot = document.querySelector('[data-home-panels]');

    if (!panelRoot) {
        return;
    }

    const panels = Array.from(panelRoot.querySelectorAll('[data-home-panel]'));
    const navLinks = Array.from(document.querySelectorAll('[data-home-panel-target]'));
    const allowedPanels = panels.map((panel) => panel.getAttribute('data-home-panel')).filter(Boolean);
    const panelRoutes = {
        posts: '/',
        hot: '/hot',
        notice: '/notice',
        guestbook: '/guestbook',
        about: '/about',
    };
    const panelByPath = Object.fromEntries(Object.entries(panelRoutes).map(([panel, path]) => [path, panel]));

    if (panels.length === 0 || navLinks.length === 0) {
        return;
    }

    const scrollHomeToTop = () => {
        window.scrollTo({
            top: 0,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    };

    const animatePanel = (panel) => {
        if (!panel || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        panel.classList.remove('is-panel-entering');
        window.requestAnimationFrame(() => {
            panel.classList.add('is-panel-entering');
        });
    };

    const setActive = (name, updateUrl = true, scrollToTop = true) => {
        const panelName = allowedPanels.includes(name) ? name : 'posts';
        let activePanel = null;

        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-home-panel') === panelName;
            panel.hidden = !isActive;
            panel.classList.toggle('is-active', isActive);
            panel.classList.remove('is-panel-entering');

            if (isActive) {
                activePanel = panel;
            }
        });

        animatePanel(activePanel);

        navLinks.forEach((link) => {
            const isActive = link.getAttribute('data-home-panel-target') === panelName;
            link.classList.toggle('is-active', isActive);
            link.setAttribute('aria-current', isActive ? 'page' : 'false');
        });

        panelRoot.setAttribute('data-active-panel', panelName);

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.pathname = panelRoutes[panelName] || '/';
            url.search = '';
            url.hash = '';

            window.history.pushState({ homePanel: panelName }, '', `${url.pathname}${url.search}${url.hash}`);
        }

        if (scrollToTop) {
            scrollHomeToTop();
        }
    };

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!panelByPath[window.location.pathname]) {
                return;
            }

            const targetUrl = new URL(link.getAttribute('href') || '/', window.location.origin);
            const targetPanel = panelByPath[targetUrl.pathname];

            if (targetUrl.origin !== window.location.origin || !targetPanel) {
                return;
            }

            event.preventDefault();
            setActive(link.getAttribute('data-home-panel-target') || targetPanel);
        });
    });

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        setActive(panelByPath[window.location.pathname] || params.get('panel') || 'posts', false);
    });

    setActive(panelRoot.getAttribute('data-active-panel') || panelByPath[window.location.pathname] || 'posts', false, false);
})();

(() => {
    const dropdown = document.querySelector('[data-category-dropdown]');
    const trigger = dropdown ? dropdown.querySelector('[data-category-trigger]') : null;
    const triggerLabel = dropdown ? dropdown.querySelector('[data-category-trigger-label]') : null;

    if (!dropdown || !trigger) {
        return;
    }

    let categoryRequest = null;
    const canHoverOpen = window.matchMedia ? window.matchMedia('(hover: hover) and (pointer: fine)').matches : false;

    const setOpen = (open) => {
        dropdown.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const setLoading = (loading) => {
        dropdown.classList.toggle('is-loading', loading);
        trigger.disabled = loading;
        trigger.setAttribute('aria-busy', loading ? 'true' : 'false');
    };

    const cleanCategoryUrl = () => {
        const url = new URL(window.location.href);

        if (!url.searchParams.has('category')) {
            return;
        }

        url.searchParams.delete('category');
        url.searchParams.delete('page');
        window.history.replaceState(window.history.state || {}, '', `${url.pathname}${url.search}${url.hash}`);
    };

    const updateCategoryState = (nextDropdown) => {
        const currentCategory = nextDropdown.getAttribute('data-current-category') || '';
        const nextLabel = nextDropdown.querySelector('[data-category-trigger-label]');

        dropdown.setAttribute('data-current-category', currentCategory);

        if (triggerLabel && nextLabel) {
            triggerLabel.textContent = nextLabel.textContent;
        }

        dropdown.querySelectorAll('[data-category-link]').forEach((link) => {
            const isActive = (link.getAttribute('data-category-slug') || '') === currentCategory;
            link.classList.toggle('is-active', isActive);
            link.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
    };

    const replacePostsFrom = (html) => {
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextPostSection = nextDocument.querySelector('.post-section');
        const currentPostSection = document.querySelector('.post-section');
        const nextDropdown = nextDocument.querySelector('[data-category-dropdown]');

        if (!nextPostSection || !currentPostSection || !nextDropdown) {
            throw new Error('Category response is missing required content.');
        }

        currentPostSection.replaceWith(nextPostSection);
        updateCategoryState(nextDropdown);
    };

    const loadCategory = async (href) => {
        const targetUrl = new URL(href, window.location.origin);

        if (targetUrl.origin !== window.location.origin) {
            return;
        }

        if (categoryRequest) {
            categoryRequest.abort();
        }

        const request = new AbortController();
        categoryRequest = request;
        if (canHoverOpen) {
            dropdown.classList.add('is-hover-paused');
        }
        setOpen(false);
        setLoading(true);

        try {
            const response = await fetch(targetUrl.href, {
                signal: request.signal,
                headers: {
                    'X-Requested-With': 'fetch',
                },
            });

            if (!response.ok) {
                throw new Error(`Failed to load category: ${response.status}`);
            }

            replacePostsFrom(await response.text());
            cleanCategoryUrl();
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
        } finally {
            if (categoryRequest === request) {
                categoryRequest = null;
                setLoading(false);
            }
        }
    };

    cleanCategoryUrl();

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(!dropdown.classList.contains('is-open'));
    });

    dropdown.addEventListener('mouseleave', () => {
        dropdown.classList.remove('is-hover-paused');
        setOpen(false);
    });

    document.addEventListener('click', (event) => {
        const categoryLink = event.target.closest('[data-category-link]');

        if (categoryLink && dropdown.contains(categoryLink)) {
            event.preventDefault();
            loadCategory(categoryLink.href);
            return;
        }

        const postPageLink = event.target.closest('[data-post-page-link]');

        if (postPageLink) {
            event.preventDefault();
            loadCategory(postPageLink.href);
            return;
        }

        if (dropdown.contains(event.target)) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();

(() => {
    const copyButtons = Array.from(document.querySelectorAll('[data-copy-value]'));

    if (copyButtons.length === 0) {
        return;
    }

    const fallbackCopy = (text) => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    };

    const setFeedback = (button, message, success) => {
        const tooltip = button.querySelector('.copy-tooltip');
        const originalLabel = button.getAttribute('data-original-label') || button.getAttribute('aria-label') || '复制';

        button.setAttribute('data-original-label', originalLabel);
        button.classList.toggle('is-copied', success);
        button.classList.toggle('is-copy-failed', !success);
        button.setAttribute('aria-label', message);

        if (tooltip) {
            tooltip.textContent = message;
        }

        window.clearTimeout(button.copyFeedbackTimer);
        button.copyFeedbackTimer = window.setTimeout(() => {
            button.classList.remove('is-copied', 'is-copy-failed');
            button.setAttribute('aria-label', originalLabel);
            if (tooltip) {
                tooltip.textContent = '';
            }
        }, 1600);
    };

    copyButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.getAttribute('data-copy-value') || '';

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    fallbackCopy(text);
                }

                setFeedback(button, '复制成功', true);
            } catch (error) {
                try {
                    fallbackCopy(text);
                    setFeedback(button, '复制成功', true);
                } catch (fallbackError) {
                    setFeedback(button, '复制失败', false);
                }
            }
        });
    });
})();

(() => {
    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) {
        return;
    }

    const track = carousel.querySelector('[data-carousel-track]');
    const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));
    const slides = Array.from(carousel.querySelectorAll('.carousel-slide'));

    if (!track || slides.length === 0) {
        return;
    }

    let current = 0;
    let timer = null;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const goTo = (index) => {
        current = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${current * 100}%)`;
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === current);
            dot.setAttribute('aria-current', dotIndex === current ? 'true' : 'false');
        });
    };

    const stop = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        if (reduceMotion || slides.length <= 1) {
            return;
        }

        stop();
        timer = window.setInterval(() => goTo(current + 1), 4200);
    };

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            goTo(index);
            start();
        });
    });

    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', start);

    goTo(0);
    start();
})();

// 浏览器指纹：综合 canvas / webgl / 屏幕 / 导航器 / 时区等特征生成 SHA-256，
// 供服务端 VisitorIdentifier 作为点赞判重主标识（取代单纯依赖本地存储的 token）
const zmoonBrowserFingerprint = (() => {
    let promise = null;

    const canvasSignal = () => {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 240;
            canvas.height = 60;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return '';
            }
            ctx.textBaseline = 'top';
            ctx.font = "16px 'Arial'";
            ctx.fillStyle = '#f60';
            ctx.fillRect(8, 6, 110, 26);
            ctx.fillStyle = '#069';
            ctx.fillText('ZMoon · 指纹 · fingerprint', 4, 32);
            ctx.strokeStyle = 'rgba(102,204,0,0.7)';
            ctx.beginPath();
            ctx.arc(60, 30, 20, 0, Math.PI * 2, true);
            ctx.stroke();
            return canvas.toDataURL();
        } catch (error) {
            return '';
        }
    };

    const webglSignal = () => {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) {
                return '';
            }
            const dbg = gl.getExtension('WEBGL_debug_renderer_info');
            const vendor = dbg ? gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL) : '';
            const renderer = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : '';
            return vendor + '|' + renderer;
        } catch (error) {
            return '';
        }
    };

    const collect = () => {
        const nav = navigator;
        const scr = window.screen || {};
        return [
            'ua:' + (nav.userAgent || ''),
            'lang:' + (nav.language || '') + '|' + ((nav.languages || []).join(',')),
            'plat:' + (nav.platform || ''),
            'hc:' + (nav.hardwareConcurrency || 0),
            'dm:' + (nav.deviceMemory || 0),
            'touch:' + (nav.maxTouchPoints || 0),
            'cook:' + (nav.cookieEnabled ? 1 : 0),
            'pdf:' + (nav.pdfViewerEnabled ? 1 : 0),
            'tz:' + (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
            'tzo:' + new Date().getTimezoneOffset(),
            'scr:' + (scr.width || 0) + 'x' + (scr.height || 0) + 'x' + (scr.colorDepth || 0),
            'avail:' + (scr.availWidth || 0) + 'x' + (scr.availHeight || 0),
            'dpr:' + (window.devicePixelRatio || 1),
            'canvas:' + canvasSignal(),
            'webgl:' + webglSignal(),
        ].join('\n');
    };

    const fallbackHash = (raw) => {
        let h1 = 0x811c9dc5 >>> 0;
        let h2 = 0x1000193 >>> 0;
        for (let i = 0; i < raw.length; i++) {
            const c = raw.charCodeAt(i);
            h1 = Math.imul(h1 ^ c, 0x01000193) >>> 0;
            h2 = Math.imul(h2 ^ c, 0x85ebca6b) >>> 0;
        }
        return (h1.toString(16).padStart(8, '0') + h2.toString(16).padStart(8, '0')).repeat(4).slice(0, 64);
    };

    const compute = async () => {
        const raw = collect();
        if (window.crypto && crypto.subtle && typeof crypto.subtle.digest === 'function') {
            try {
                const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(raw));
                return Array.from(new Uint8Array(buf)).map((b) => b.toString(16).padStart(2, '0')).join('');
            } catch (error) {
                // 落入兜底哈希
            }
        }
        return fallbackHash(raw);
    };

    const get = () => {
        if (!promise) {
            promise = compute();
        }
        return promise;
    };

    // 尽早写入 cookie，供后续服务端首屏渲染按指纹判重
    get().then((hex) => {
        if (/^[a-f0-9]{64}$/.test(hex)) {
            document.cookie = 'blog_browser_fingerprint=' + hex + ';path=/;max-age=31536000;SameSite=Lax';
        }
    }).catch(() => {});

    return get;
})();

(() => {
    const inputs = document.querySelectorAll('[data-browser-fingerprint-input]');

    if (!inputs.length || typeof zmoonBrowserFingerprint !== 'function') {
        return;
    }

    const setFingerprint = async () => {
        try {
            const fingerprint = await zmoonBrowserFingerprint();
            if (!/^[a-f0-9]{64}$/.test(fingerprint)) {
                return '';
            }
            inputs.forEach((input) => {
                input.value = fingerprint;
            });
            return fingerprint;
        } catch (error) {
            return '';
        }
    };

    setFingerprint();

    inputs.forEach((input) => {
        const form = input.closest('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            if (/^[a-f0-9]{64}$/.test(input.value) || form.dataset.browserFingerprintReady === '1') {
                return;
            }

            event.preventDefault();
            await setFingerprint();
            form.dataset.browserFingerprintReady = '1';
            form.requestSubmit();
        });
    });
})();

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
(() => {
    const showToast = (message, duration = 3000, type = 'error') => {
        const container = document.querySelector('[data-toast-container]');

        if (!container) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type === 'success' ? 'success' : 'error'}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        const hideTimer = setTimeout(() => {
            toast.classList.add('is-hiding');

            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);

        toast.addEventListener('click', () => {
            clearTimeout(hideTimer);
            toast.classList.add('is-hiding');

            setTimeout(() => {
                toast.remove();
            }, 300);
        });
    };

    window.showToast = showToast;
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

// 文章详情页：摘要按钮展开/收起站长摘要
(() => {
    const toggle = document.querySelector('[data-summary-toggle]');
    const panel = document.querySelector('[data-summary-panel]');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        panel.hidden = expanded;
    });
})();
