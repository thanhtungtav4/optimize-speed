(function () {
    function initLQIP() {
        const images = document.querySelectorAll('img.opti-lqip');
        if (!images.length) return;

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;

                    // Swap src sources
                    if (img.dataset.src) img.src = img.dataset.src;
                    if (img.dataset.srcset) img.srcset = img.dataset.srcset;
                    if (img.dataset.sizes) img.sizes = img.dataset.sizes;

                    // When real image loads/errors, remove blur
                    img.onload = () => {
                        img.classList.remove('opti-lqip');
                        img.removeAttribute('data-src');
                        img.removeAttribute('data-srcset');
                        img.removeAttribute('data-sizes');
                    };
                    img.onerror = () => {
                        img.classList.remove('opti-lqip');
                    };

                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: "50px 0px", // Preload just before view
            threshold: 0.01
        });

        images.forEach(img => observer.observe(img));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLQIP);
    } else {
        initLQIP();
    }
})();
