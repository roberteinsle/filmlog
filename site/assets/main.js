// Filmlog website: scroll reveals, word by word tagline, current section in the navigation.
(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Sections rise and fade in once.
    const revealables = document.querySelectorAll('.reveal');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealables.forEach((element) => element.classList.add('is-visible'));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px' });
        revealables.forEach((element) => revealObserver.observe(element));
    }

    // Tagline: every word lights up as it crosses a line at 60 % of the viewport height.
    const tagline = document.querySelector('[data-tagline]');
    if (tagline) {
        const words = tagline.textContent.trim().split(/\s+/);
        tagline.textContent = '';
        words.forEach((word, index) => {
            const span = document.createElement('span');
            span.className = 'word' + (/^Never$/.test(word) ? ' accent-word' : '');
            span.textContent = word;
            tagline.append(span, index < words.length - 1 ? ' ' : '');
        });

        const spans = tagline.querySelectorAll('.word');
        if (reduceMotion || !('IntersectionObserver' in window)) {
            spans.forEach((span) => span.classList.add('is-lit'));
        } else {
            const wordObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-lit');
                        wordObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -40% 0px', threshold: 1 });
            spans.forEach((span) => wordObserver.observe(span));
        }
    }

    // Navigation: mark the section currently in view.
    const links = [...document.querySelectorAll('.nav-links a[href^="#"]')];
    const sections = links.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    if (sections.length && 'IntersectionObserver' in window) {
        const navObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                links.forEach((link) => link.setAttribute('aria-current', link.getAttribute('href') === '#' + entry.target.id ? 'true' : 'false'));
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        sections.forEach((section) => navObserver.observe(section));
    }
})();
