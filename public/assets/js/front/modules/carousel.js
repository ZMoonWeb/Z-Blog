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
