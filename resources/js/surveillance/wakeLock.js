// Keeps the screen awake for the whole night; the OS releases the lock when
// the tab is hidden, so re-acquire it whenever the page becomes visible again.
export class WakeLock {
    constructor(onUnsupported) {
        this.sentinel = null;
        this.onUnsupported = onUnsupported ?? (() => {});
        this.onVisibilityChange = () => {
            if (document.visibilityState === 'visible' && this.sentinel !== null) {
                this.acquire();
            }
        };
    }

    async acquire() {
        if (!('wakeLock' in navigator)) {
            this.onUnsupported();

            return;
        }

        try {
            this.sentinel = await navigator.wakeLock.request('screen');
            document.addEventListener('visibilitychange', this.onVisibilityChange);
        } catch {
            this.onUnsupported();
        }
    }

    async release() {
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        await this.sentinel?.release();
        this.sentinel = null;
    }
}
