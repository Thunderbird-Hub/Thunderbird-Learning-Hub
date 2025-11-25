(function () {
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    let deferredPrompt = null;
    let bannerRendered = false;

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        navigator.serviceWorker.register('/assets/pwa/service-worker.js').catch(() => {
            // Registration failure is non-blocking for install UI
        });
    }

    function hideBanner() {
        const existing = document.querySelector('.pwa-install-banner');
        if (existing) {
            existing.remove();
        }
        bannerRendered = false;
    }

    function createBanner() {
        if (bannerRendered || isStandalone) {
            return;
        }

        const banner = document.createElement('div');
        banner.className = 'pwa-install-banner';
        banner.innerHTML = `
            <div>
                <h3>Add to Home Screen</h3>
                <p>Install this portal for faster access and better offline support.</p>
            </div>
            <div class="pwa-install-actions">
                <button type="button" class="btn btn-secondary" data-action="dismiss">Not now</button>
                <button type="button" class="btn btn-primary" data-action="install">Install</button>
            </div>
        `;

        banner.addEventListener('click', (event) => {
            const action = event.target.getAttribute('data-action');
            if (action === 'dismiss') {
                localStorage.setItem('pwaInstallDismissed', Date.now().toString());
                hideBanner();
            } else if (action === 'install') {
                if (!deferredPrompt) {
                    hideBanner();
                    return;
                }
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(() => {
                    hideBanner();
                    deferredPrompt = null;
                });
            }
        });

        document.body.appendChild(banner);
        bannerRendered = true;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        const dismissedAt = parseInt(localStorage.getItem('pwaInstallDismissed') || '0', 10);
        const twoDays = 1000 * 60 * 60 * 24 * 2;
        const recentlyDismissed = dismissedAt && (Date.now() - dismissedAt < twoDays);

        if (recentlyDismissed || isStandalone) {
            return;
        }

        event.preventDefault();
        deferredPrompt = event;
        createBanner();
    });

    window.addEventListener('appinstalled', () => {
        localStorage.removeItem('pwaInstallDismissed');
        hideBanner();
    });

    registerServiceWorker();
})();
