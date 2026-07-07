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
