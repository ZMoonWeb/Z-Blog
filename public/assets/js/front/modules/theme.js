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
