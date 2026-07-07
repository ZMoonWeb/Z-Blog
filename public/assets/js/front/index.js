const markFrontJsReady = () => {
    document.documentElement.classList.add('js-ready');
};

const readFrontCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const metaToken = meta ? String(meta.getAttribute('content') || '') : '';
    if (metaToken !== '') {
        return metaToken;
    }

    const input = document.querySelector('input[name="_csrf"]');
    return input ? String(input.value || '') : '';
};

const frontCsrfHeaders = () => {
    const token = readFrontCsrfToken();
    return token !== '' ? { 'X-CSRF-Token': token } : {};
};
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
