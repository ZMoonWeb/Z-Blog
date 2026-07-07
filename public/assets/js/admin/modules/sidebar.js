function toggleAdminSidebar() {
    const body = document.body;
    const toggle = document.querySelector('.admin-sidebar-toggle');
    const isOpen = body.classList.toggle('admin-sidebar-open');

    if (toggle) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('aria-label', isOpen ? '收起侧边栏' : '展开侧边栏');
    }
}

function closeAdminSidebar() {
    document.body.classList.remove('admin-sidebar-open');

    const toggle = document.querySelector('.admin-sidebar-toggle');
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', '展开侧边栏');
    }
}

function initAdminSidebar() {
    document.querySelectorAll('.admin-nav-item').forEach((item) => {
        item.addEventListener('click', (event) => {
            if (window.__zblogAdminUpdatePending === true) {
                event.preventDefault();
                return;
            }

            if (window.matchMedia && window.matchMedia('(max-width: 980px)').matches) {
                closeAdminSidebar();
            }
            showMainLoader();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAdminSidebar();
        }
    });
}

function showMainLoader() {
    const main = document.querySelector('.admin-main');
    if (!main || main.querySelector('.admin-main-loader')) {
        return;
    }
    main.classList.add('is-loading');
    const loader = document.createElement('div');
    loader.className = 'admin-main-loader';
    loader.innerHTML = '<span class="admin-main-loader-spinner"></span>';
    main.appendChild(loader);
}

function initAdminHeader() {
    const headers = document.querySelectorAll('.admin-shell-header, [data-admin-header]');

    if (headers.length === 0) {
        return;
    }

    const syncHeaderState = () => {
        const isScrolled = window.scrollY > 2;
        headers.forEach((header) => {
            header.classList.toggle('is-scrolled', isScrolled);
        });
    };

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
}

initAdminHeader();
initAdminSidebar();
