// @vitest-environment happy-dom

// Drives the capture page the way a person does: mount the markup, click the
// buttons, and check what reaches the network. The camera, uploader, wake lock
// and calibration are stubbed; the detector and tracker are the real ones.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

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

function mountPage() {
    const config = { csrfToken: 'test-csrf-token', routes: ROUTES };

    document.body.innerHTML = `
        <section id="capture-app" data-config='${JSON.stringify(config)}'>
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

/** Load capture.js against the mounted page and let its start-up chain settle. */
async function bootCaptureApp() {
    vi.resetModules();
    await import('../../../resources/js/surveillance/capture.js');
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
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    async function startWatching() {
        el('start').click();
        await settle();
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
        window.confirm = vi.fn(() => false);
        await startWatching();

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

    test('leaving the page mid-night flushes whatever is queued', async () => {
        await startWatching();
        stubs.uploaderFlush.mockClear();

        window.dispatchEvent(new Event('pagehide'));

        expect(stubs.uploaderFlush).toHaveBeenCalledWith({ keepalive: true });
    });
});
