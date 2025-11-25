(function() {
    const initTabNavigation = () => {
        const tabLinks = document.querySelectorAll('.mobile-tab-bar a');
        const scrollLinks = Array.from(tabLinks).filter((link) => Boolean(link.dataset.target));
        const sectionIds = scrollLinks.map((link) => link.dataset.target);

        if (!tabLinks.length || !sectionIds.length) {
            return;
        }

        scrollLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const targetId = link.dataset.target;
                if (!targetId) {
                    return;
                }

                const linkUrl = new URL(link.href, window.location.href);
                const hasHash = linkUrl.hash !== '';

                // Only intercept clicks for anchor links (same page with hash)
                if (hasHash && linkUrl.pathname === window.location.pathname) {
                    const targetEl = document.getElementById(targetId);
                    if (targetEl) {
                        event.preventDefault();
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

                        // Keep the hash in sync for browsers that rely on it
                        if (typeof history !== 'undefined' && history.replaceState) {
                            history.replaceState(null, '', `#${targetId}`);
                        } else {
                            window.location.hash = targetId;
                        }
                    }
                }
                // For regular navigation to different pages, let the browser handle it naturally
            });
        });

        const sectionMap = {};
        sectionIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) {
                sectionMap[id] = el;
            }
        });

        const updateActiveTab = (activeId) => {
            tabLinks.forEach((link) => {
                link.classList.toggle('active', link.dataset.target === activeId);
            });
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        updateActiveTab(entry.target.id);
                    }
                });
            },
            { threshold: 0.4 }
        );

        Object.values(sectionMap).forEach((section) => observer.observe(section));
    };

    const registerServiceWorker = () => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/assets/pwa/service-worker.js').catch((error) => {
                console.error('Service worker registration failed:', error);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTabNavigation();
        registerServiceWorker();
    });
})();
