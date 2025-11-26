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

        const visibleSections = new Map();
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    const sectionId = entry.target.id;

                    if (entry.isIntersecting) {
                        visibleSections.set(sectionId, entry.intersectionRatio);
                    } else {
                        visibleSections.delete(sectionId);
                    }
                });

                if (!visibleSections.size) {
                    return;
                }

                const sortedVisibleSections = Array.from(visibleSections.entries()).sort(
                    (a, b) =>
                        b[1] - a[1] ||
                        sectionMap[a[0]].getBoundingClientRect().top - sectionMap[b[0]].getBoundingClientRect().top
                );

                if (sortedVisibleSections.length) {
                    updateActiveTab(sortedVisibleSections[0][0]);
                }
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
