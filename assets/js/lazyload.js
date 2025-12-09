document.addEventListener('DOMContentLoaded', function () {
    const lazyIframes = document.querySelectorAll('.os-lazy-iframe');

    lazyIframes.forEach(function (wrapper) {
        wrapper.addEventListener('click', function () {
            const src = this.getAttribute('data-src');
            const title = this.getAttribute('data-title') || 'Video';

            if (src) {
                const iframe = document.createElement('iframe');
                iframe.setAttribute('src', src);
                iframe.setAttribute('title', title);
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                iframe.setAttribute('allowfullscreen', 'true');

                // Copy classes if needed
                iframe.classList.add('os-loaded-iframe');

                this.innerHTML = '';
                this.appendChild(iframe);
            }
        });
    });
});
