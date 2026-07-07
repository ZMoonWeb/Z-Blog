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

initAdminTheme();
