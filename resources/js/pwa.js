import Alpine from '@alpinejs/csp';

let installEvent = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installEvent = event;
    window.dispatchEvent(new CustomEvent('pwa:installable'));
});

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true;

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

Alpine.data('pwaInstall', () => ({
    canPrompt: false,
    showIosGuide: false,
    showGuideText: false,
    installed: isStandalone(),
    init() {
        this.canPrompt = installEvent !== null;
        this.showIosGuide = isIos() && !this.installed;

        window.addEventListener('pwa:installable', () => {
            this.canPrompt = true;
        });
        window.addEventListener('appinstalled', () => {
            this.installed = true;
            this.canPrompt = false;
        });
    },
    get showButton() {
        return this.canPrompt && !this.installed;
    },
    async install() {
        if (!installEvent) {
            return;
        }

        installEvent.prompt();
        await installEvent.userChoice;
        installEvent = null;
        this.canPrompt = false;
    },
    toggleGuide() {
        this.showGuideText = !this.showGuideText;
    },
}));

Alpine.data('pwaUpdate', () => ({
    waiting: null,
    init() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.register('/sw.js').then((registration) => {
            if (registration.waiting && navigator.serviceWorker.controller) {
                this.waiting = registration.waiting;
            }

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;

                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.waiting = worker;
                    }
                });
            });
        }).catch(() => {
            // The app works without a worker; it just cannot be used offline.
        });

        let reloading = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (this.waiting && !reloading) {
                reloading = true;
                window.location.reload();
            }
        });
    },
    get hasUpdate() {
        return this.waiting !== null;
    },
    refresh() {
        this.waiting?.postMessage('SKIP_WAITING');
    },
}));

const urlBase64ToUint8Array = (value) => {
    const padded = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
};

Alpine.data('pushToggle', () => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    subscribed: false,
    denied: false,
    async init() {
        if (!this.supported) {
            return;
        }

        this.denied = Notification.permission === 'denied';
        const registration = await navigator.serviceWorker.ready;
        this.subscribed = (await registration.pushManager.getSubscription()) !== null;
    },
    get label() {
        return this.subscribed ? this.$el.dataset.offLabel ?? 'Turn off notifications' : this.$el.dataset.onLabel ?? 'Turn on notifications';
    },
    post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(body),
        });
    },
    async toggle() {
        const root = this.$root;
        const registration = await navigator.serviceWorker.ready;
        const current = await registration.pushManager.getSubscription();

        if (current) {
            await this.post(root.dataset.unsubscribeUrl, { endpoint: current.endpoint });
            await current.unsubscribe();
            this.subscribed = false;

            return;
        }

        const permission = await Notification.requestPermission();
        this.denied = permission === 'denied';

        if (permission !== 'granted') {
            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(root.dataset.publicKey),
        });
        await this.post(root.dataset.subscribeUrl, subscription.toJSON());
        this.subscribed = true;
    },
}));
