// @vitest-environment happy-dom

// Drives the capture page the way a person does: mount the markup, click the
// buttons, and check what reaches the network. The camera, uploader, wake lock
// and calibration are stubbed; the detector and tracker are the real ones.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { LEAVE_ROOM_SECONDS } from '../../../resources/js/surveillance/captureLogic.js';

const stubs = vi.hoisted(() => ({
    cameraStart: vi.fn(async () => {}),
    cameraStop: vi.fn(),
    captureReferenceJpeg: vi.fn(async () => new Blob(['jpeg'], { type: 'image/jpeg' })),
    grabProcessedFrame: vi.fn(() => ({
        width: 8,
        height: 8,
        data: new Uint8ClampedArray(8 * 8 * 4).fill(200),
    })),
    calibrate: vi.fn(async () => ({ meanLuminance: 120, tooDark: false, dim: false, diffThreshold: 20 })),
    uploaderStart: vi.fn(),
    uploaderStop: vi.fn(),
    uploaderFlush: vi.fn(async () => {}),
    uploaderEnqueue: vi.fn(),
    uploaderPost: vi.fn(async () => ({
        ok: true,
        json: async () => ({ report_url: 'https://bugtracker.test/surveillance/1/report' }),
    })),
    wakeAcquire: vi.fn(async () => {}),
    wakeRelease: vi.fn(async () => {}),
}));

vi.mock('../../../resources/js/surveillance/camera.js', () => ({
    Camera: class {
        scale = 4;
        frameWidth = 1280;
        frameHeight = 720;
        procCanvas = { width: 8, height: 8 };
        start = stubs.cameraStart;
        stop = stubs.cameraStop;
        captureReferenceJpeg = stubs.captureReferenceJpeg;
        grabProcessedFrame = stubs.grabProcessedFrame;
        captureCropBase64 = () => 'crop-data';
    },
}));

vi.mock('../../../resources/js/surveillance/brightness.js', async (importOriginal) => ({
    ...(await importOriginal()),
    calibrate: stubs.calibrate,
}));

vi.mock('../../../resources/js/surveillance/uploader.js', () => ({
    Uploader: class {
        start = stubs.uploaderStart;
        stop = stubs.uploaderStop;
        flush = stubs.uploaderFlush;
        enqueue = stubs.uploaderEnqueue;
        post = stubs.uploaderPost;
    },
}));

vi.mock('../../../resources/js/surveillance/wakeLock.js', () => ({
    WakeLock: class {
        /** Hold on to the callback so a test can fire the unsupported path. */
        constructor(onUnsupported) {
            stubs.wakeLockUnsupported = onUnsupported;
        }

        acquire = stubs.wakeAcquire;

        release = stubs.wakeRelease;
    },
}));

const ROUTES = {
    reference: 'https://bugtracker.test/surveillance/1/reference',
    tracks: 'https://bugtracker.test/surveillance/1/tracks',
    heartbeat: 'https://bugtracker.test/surveillance/1/heartbeat',
    end: 'https://bugtracker.test/surveillance/1/end',
};

const el = (name) => document.querySelector(`[data-capture="${name}"]`);
const nav = () => document.querySelector('[data-app-nav]');

function mountPage() {
    const config = { csrfToken: 'test-csrf-token', routes: ROUTES };

    document.body.innerHTML = `
        <nav data-app-nav>sidebar</nav>
        <section id="capture-app" data-config='${JSON.stringify(config)}'>
            <button data-capture="check"><span data-capture="check-label">Check camera</span></button>
            <button data-capture="start">Start watching</button>
            <button data-capture="end" class="hidden">End night</button>
            <button data-capture="abort" class="hidden">Discard night</button>
            <div data-capture="banner" class="hidden"></div>
            <video data-capture="video"></video>
            <canvas data-capture="overlay"></canvas>
            <span data-capture="state"></span>
            <span data-capture="elapsed"></span>
            <span data-capture="track-count"></span>
            <span data-capture="live-count"></span>
            <span data-capture="queue-depth"></span>
            <span data-capture="brightness"></span>
            <input type="checkbox" data-capture="debug-toggle" checked />
            <div data-capture="setup-help">If the screen keeps sleeping…</div>
        </section>
    `;

    // happy-dom has no 2d context; the overlay only needs to accept the calls.
    el('overlay').getContext = () => ({
        clearRect: vi.fn(),
        strokeRect: vi.fn(),
        strokeStyle: '',
        lineWidth: 0,
    });
}

/**
 * Every boot re-imports capture.js and registers another set of window listeners,
 * but happy-dom's window outlives the whole file. Left alone they pile up, and a
 * previous test's app — still mid-night as far as its closure knows — answers
 * events meant for this one. Recorded here so afterEach can take them off again.
 */
const windowListeners = [];

/** Load capture.js against the mounted page and let its start-up chain settle. */
async function bootCaptureApp() {
    const addEventListener = window.addEventListener.bind(window);

    vi.spyOn(window, 'addEventListener').mockImplementation((type, handler, options) => {
        windowListeners.push([type, handler, options]);
        addEventListener(type, handler, options);
    });

    vi.resetModules();
    await import('../../../resources/js/surveillance/capture.js');

    window.addEventListener.mockRestore();
}

function removeTrackedWindowListeners() {
    for (const [type, handler, options] of windowListeners.splice(0)) {
        window.removeEventListener(type, handler, options);
    }
}

/** Let awaited promise chains resolve without letting the frame loop run. */
async function settle() {
    await vi.advanceTimersByTimeAsync(0);
}

describe('capture page', () => {
    beforeEach(async () => {
        vi.useFakeTimers();
        vi.clearAllMocks();
        stubs.calibrate.mockResolvedValue({ meanLuminance: 120, tooDark: false, dim: false, diffThreshold: 20 });
        stubs.cameraStart.mockResolvedValue(undefined);
        stubs.uploaderPost.mockResolvedValue({
            ok: true,
            json: async () => ({ report_url: 'https://bugtracker.test/surveillance/1/report' }),
        });

        mountPage();
        window.confirm = vi.fn(() => true);
        window.location.assign = vi.fn();
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true })));

        await bootCaptureApp();
    });

    afterEach(() => {
        removeTrackedWindowListeners();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    /** Click start and sit through the leave-the-room countdown. */
    async function startWatching() {
        el('start').click();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);
    }

    test('starting a night uploads the reference frame and begins watching', async () => {
        await startWatching();

        expect(stubs.cameraStart).toHaveBeenCalled();
        expect(stubs.uploaderStart).toHaveBeenCalled();
        expect(stubs.wakeAcquire).toHaveBeenCalled();
        expect(el('state').textContent).toBe('Watching');
        expect(el('brightness').textContent).toBe('120 / 255');

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe(ROUTES.reference);
        expect(options.method).toBe('POST');
        expect(options.headers['X-CSRF-TOKEN']).toBe('test-csrf-token');
        expect(options.body.get('frame_width')).toBe('1280');
        expect(options.body.get('settings[procWidth]')).toBe('320');
    });

    test('the camera check opens the preview without starting a night', async () => {
        el('check').click();
        await settle();

        expect(stubs.cameraStart).toHaveBeenCalled();
        expect(window.confirm).not.toHaveBeenCalled();
        expect(stubs.calibrate).not.toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
        expect(el('check-label').textContent).toBe('Stop camera');
        expect(el('state').textContent).toContain('aim the device');
    });

    test('the camera check closes the preview again when pressed a second time', async () => {
        el('check').click();
        await settle();
        el('check').click();
        await settle();

        expect(stubs.cameraStop).toHaveBeenCalledTimes(1);
        expect(el('check-label').textContent).toBe('Check camera');
        expect(el('state').textContent).toBe('Idle');
    });

    /**
     * Two live streams would leave the check's tracks — and the device's recording
     * light — running all night behind the one the night is actually watching.
     */
    test('starting a night closes an open camera check first', async () => {
        el('check').click();
        await settle();

        await startWatching();

        expect(stubs.cameraStop).toHaveBeenCalledTimes(1);
        expect(stubs.cameraStart).toHaveBeenCalledTimes(2);
        expect(stubs.cameraStop.mock.invocationCallOrder[0])
            .toBeLessThan(stubs.cameraStart.mock.invocationCallOrder[1]);
        expect(el('state').textContent).toBe('Watching');
    });

    test('backing out of the checklist leaves an open camera check alone', async () => {
        el('check').click();
        await settle();

        window.confirm = vi.fn(() => false);
        el('start').click();
        await settle();

        expect(stubs.cameraStop).not.toHaveBeenCalled();
        expect(el('check-label').textContent).toBe('Stop camera');
    });

    test('the camera check makes way once the night is under way', async () => {
        await startWatching();

        expect(el('check').classList.contains('hidden')).toBe(true);
    });

    test('a refused camera leaves the check button usable', async () => {
        stubs.cameraStart.mockRejectedValue(new Error('Permission denied'));

        el('check').click();
        await settle();

        expect(el('state').textContent).toBe('Error');
        expect(el('banner').textContent).toContain('Permission denied');
        expect(el('check-label').textContent).toBe('Check camera');
    });

    test('the room checklist is put in front of the user before the camera opens', async () => {
        el('start').click();
        await settle();

        const [message] = window.confirm.mock.calls[0];
        expect(message).toContain('Turn on a light');
        expect(message).toContain('Turn off fans');
        expect(message).toContain('changing colour reads as movement');
    });

    test('backing out of the checklist starts nothing and leaves the button usable', async () => {
        window.confirm = vi.fn(() => false);

        await startWatching();

        expect(stubs.cameraStart).not.toHaveBeenCalled();
        expect(stubs.calibrate).not.toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
        expect(el('start').hasAttribute('disabled')).toBe(false);
        expect(el('state').textContent).toBe('');
    });

    test('nothing is measured until the user has had five seconds to leave', async () => {
        el('start').click();
        await settle();

        // The preview is live so the room can be framed, but the scene is untouched.
        expect(stubs.cameraStart).toHaveBeenCalled();
        expect(el('state').textContent).toBe('Leave the room — starting in 5…');
        expect(stubs.calibrate).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(3000);

        expect(el('state').textContent).toBe('Leave the room — starting in 2…');
        expect(stubs.calibrate).not.toHaveBeenCalled();
        expect(stubs.captureReferenceJpeg).not.toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);

        expect(stubs.calibrate).toHaveBeenCalled();
        expect(el('state').textContent).toBe('Watching');
    });

    test('a device that will not hold its screen on says which setting to change', async () => {
        await startWatching();

        stubs.wakeLockUnsupported();

        expect(el('banner').classList.contains('hidden')).toBe(false);
        expect(el('banner').textContent).toContain('would not keep its screen on');
    });

    test('the app navigation is locked while recording, so a stray tap cannot end the night', async () => {
        expect(nav().hasAttribute('inert')).toBe(false);

        await startWatching();

        expect(nav().hasAttribute('inert')).toBe(true);
        expect(nav().classList.contains('opacity-40')).toBe(true);
    });

    test('navigation comes back when the night is over', async () => {
        await startWatching();

        el('end').click();
        await settle();

        expect(nav().hasAttribute('inert')).toBe(false);
        expect(nav().classList.contains('opacity-40')).toBe(false);
    });

    test('navigation comes back even when the night could not be ended', async () => {
        await startWatching();
        stubs.uploaderPost.mockResolvedValue({ ok: false, status: 500 });

        el('end').click();
        await settle();

        // Stranded on the page with an error banner: locking them out too would
        // leave no way off it at all.
        expect(nav().hasAttribute('inert')).toBe(false);
    });

    test('the setup advice makes way once the night is under way', async () => {
        expect(el('setup-help').classList.contains('hidden')).toBe(false);

        await startWatching();

        expect(el('setup-help').classList.contains('hidden')).toBe(true);
    });

    test('the end and discard buttons only appear once watching', async () => {
        expect(el('end').classList.contains('hidden')).toBe(true);
        expect(el('abort').classList.contains('hidden')).toBe(true);

        await startWatching();

        expect(el('start').classList.contains('hidden')).toBe(true);
        expect(el('end').classList.contains('hidden')).toBe(false);
        expect(el('abort').classList.contains('hidden')).toBe(false);
    });

    test('a pitch-black room is refused before anything is uploaded', async () => {
        stubs.calibrate.mockResolvedValue({ meanLuminance: 2, tooDark: true, dim: true, diffThreshold: 14 });

        await startWatching();

        expect(el('state').textContent).toBe('Too dark');
        expect(el('banner').textContent).toContain('pitch black');
        expect(el('banner').classList.contains('hidden')).toBe(false);
        expect(stubs.cameraStop).toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
        expect(el('start').hasAttribute('disabled')).toBe(false);
    });

    test('a dim room is warned about but still watched', async () => {
        stubs.calibrate.mockResolvedValue({ meanLuminance: 9, tooDark: false, dim: true, diffThreshold: 16 });

        await startWatching();

        expect(el('banner').textContent).toContain('very dim');
        expect(el('state').textContent).toBe('Watching');
    });

    test('a refused camera leaves the start button usable', async () => {
        stubs.cameraStart.mockRejectedValue(new Error('Permission denied'));

        await startWatching();

        expect(el('banner').textContent).toContain('Permission denied');
        expect(el('start').hasAttribute('disabled')).toBe(false);
        expect(el('state').textContent).toBe('Error');
    });

    test('a failed reference upload leaves the start button usable', async () => {
        fetch.mockResolvedValue({ ok: false, status: 422 });

        await startWatching();

        expect(el('banner').textContent).toContain('422');
        expect(el('start').hasAttribute('disabled')).toBe(false);
    });

    test('ending the night reports it as kept, then goes to the report', async () => {
        await startWatching();

        el('end').click();
        await settle();

        const [url, payload] = stubs.uploaderPost.mock.calls[0];
        expect(url).toBe(ROUTES.end);
        expect(payload.aborted).toBe(false);
        expect(payload.ended_at_offset_ms).toBeGreaterThanOrEqual(0);

        expect(stubs.uploaderFlush).toHaveBeenCalledWith({ keepalive: true });
        expect(stubs.cameraStop).toHaveBeenCalled();
        expect(stubs.wakeRelease).toHaveBeenCalled();
        expect(window.location.assign).toHaveBeenCalledWith('https://bugtracker.test/surveillance/1/report');
    });

    test('discarding the night reports it as aborted', async () => {
        await startWatching();

        el('abort').click();
        await settle();

        expect(window.confirm).toHaveBeenCalled();
        expect(stubs.uploaderPost.mock.calls[0][1].aborted).toBe(true);
    });

    test('backing out of the discard prompt leaves the night running', async () => {
        await startWatching();
        window.confirm = vi.fn(() => false);

        el('abort').click();
        await settle();

        expect(stubs.uploaderPost).not.toHaveBeenCalled();
        expect(el('state').textContent).toBe('Watching');
    });

    test('the buttons do nothing before a night has started', async () => {
        el('end').click();
        el('abort').click();
        await settle();

        expect(stubs.uploaderPost).not.toHaveBeenCalled();
    });

    test('a failed end keeps the user on the page and explains why', async () => {
        await startWatching();
        stubs.uploaderPost.mockResolvedValue({ ok: false, status: 500 });

        el('end').click();
        await settle();

        expect(window.location.assign).not.toHaveBeenCalled();
        expect(el('state').textContent).toBe('Error');
        expect(el('banner').textContent).toContain('500');
    });

    test('the frame loop runs while watching and stops once the night ends', async () => {
        await startWatching();

        await vi.advanceTimersByTimeAsync(1000);
        const framesWhileWatching = stubs.grabProcessedFrame.mock.calls.length;
        expect(framesWhileWatching).toBeGreaterThan(0);

        el('end').click();
        await settle();

        await vi.advanceTimersByTimeAsync(1000);
        expect(stubs.grabProcessedFrame.mock.calls.length).toBe(framesWhileWatching);
    });

    test('closing the tab mid-night is challenged first', async () => {
        await startWatching();

        const leaving = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(leaving);

        expect(leaving.defaultPrevented).toBe(true);
    });

    test('leaving is not challenged before a night has started', async () => {
        const leaving = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(leaving);

        expect(leaving.defaultPrevented).toBe(false);
    });

    test('the trip to the report is not challenged', async () => {
        await startWatching();

        el('end').click();
        await settle();

        const leaving = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(leaving);

        expect(leaving.defaultPrevented).toBe(false);
    });

    test('leaving the page mid-night flushes whatever is queued', async () => {
        await startWatching();
        stubs.uploaderFlush.mockClear();

        window.dispatchEvent(new Event('pagehide'));

        expect(stubs.uploaderFlush).toHaveBeenCalledWith({ keepalive: true });
    });
});
