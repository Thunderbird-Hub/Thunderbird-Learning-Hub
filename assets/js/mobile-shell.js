(function() {
    const initTabNavigation = () => {
        const tabLinks = document.querySelectorAll('.mobile-tab-bar a');
        const sectionIds = Array.from(tabLinks).map((link) => link.dataset.target);

        if (!tabLinks.length || !sectionIds.length) {
            return;
        }

        tabLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = link.dataset.target;
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
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
