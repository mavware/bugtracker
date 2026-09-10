// @vitest-environment happy-dom

// Drives the capture page the way a person does: mount the markup, click the
// buttons, and check what reaches the network. The camera, uploader, wake lock
// and calibration are stubbed; the detector and tracker are the real ones.
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { LARGE_MOTION_MESSAGE, LEAVE_ROOM_SECONDS } from '@mavware/bug-surveillance';
import { makeFrame, paintRect } from '../helpers.js';

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
    uploaderStoreReference: vi.fn(async () => {}),
    uploaderEnd: vi.fn(async () => ({ ok: true, status: 200, reportUrl: 'https://bugtracker.test/surveillance/1/report' })),
    localSinkStart: vi.fn(),
    localSinkStop: vi.fn(),
    localSinkFlush: vi.fn(async () => {}),
    localSinkEnqueue: vi.fn(),
    localSinkStoreReference: vi.fn(async () => {}),
    localSinkEnd: vi.fn(async () => ({ ok: true, status: 200, reportUrl: 'https://bugtracker.test/watch/local-night-id/report' })),
    localSinkOptions: null,
    openNightStore: vi.fn(async () => ({ volatile: false })),
    wakeAcquire: vi.fn(async () => {}),
    wakeRelease: vi.fn(async () => {}),
}));

// The library is mocked in part: the camera, calibration, wake lock and the
// local sink are stubbed, while the detector, tracker and everything pure stay real.
vi.mock('@mavware/bug-surveillance', async (importOriginal) => ({
    ...(await importOriginal()),
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
    calibrate: stubs.calibrate,
    WakeLock: class {
        /** Hold on to the callback so a test can fire the unsupported path. */
        constructor(onUnsupported) {
            stubs.wakeLockUnsupported = onUnsupported;
        }

        acquire = stubs.wakeAcquire;

        release = stubs.wakeRelease;
    },
    LocalNightSink: class {
        /** Keep the options so a test can check which store and route it was handed. */
        constructor(options) {
            stubs.localSinkOptions = options;
        }

        start = stubs.localSinkStart;
        stop = stubs.localSinkStop;
        flush = stubs.localSinkFlush;
        enqueue = stubs.localSinkEnqueue;
        storeReference = stubs.localSinkStoreReference;
        end = stubs.localSinkEnd;
    },
    openNightStore: stubs.openNightStore,
}));

vi.mock('../../../resources/js/surveillance/uploader.js', () => ({
    Uploader: class {
        start = stubs.uploaderStart;
        stop = stubs.uploaderStop;
        flush = stubs.uploaderFlush;
        enqueue = stubs.uploaderEnqueue;
        storeReference = stubs.uploaderStoreReference;
        end = stubs.uploaderEnd;
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

const LOCAL_CONFIG = {
    mode: 'local',
    authenticated: false,
    csrfToken: 'test-csrf-token',
    routes: { report: 'https://bugtracker.test/watch/00000000-0000-4000-8000-000000000000/report', watch: 'https://bugtracker.test/watch' },
};

function mountPage(config = { csrfToken: 'test-csrf-token', routes: ROUTES }) {

    document.body.innerHTML = `
        <nav data-app-nav>sidebar</nav>
        <section id="capture-app" data-config='${JSON.stringify(config)}'>
            <span data-capture="idle-light"></span>
            <span data-capture="live-light" class="hidden"></span>
            <span data-capture="started" class="hidden">Started <span data-capture="started-at"></span></span>
            <button data-capture="check"><span data-capture="check-label">Check camera</span></button>
            <button data-capture="start">Start watching</button>
            <button data-capture="end" class="hidden">End night</button>
            <div data-capture="banner" class="hidden"></div>
            <div data-capture="placeholder">sample room</div>
            <video data-capture="video" class="hidden"></video>
            <canvas data-capture="overlay"></canvas>
            <span data-capture="state"></span>
            <span data-capture="elapsed" class="hidden"></span>
            <span data-capture="track-count"></span>
            <span data-capture="live-count"></span>
            <span data-capture="queue-depth"></span>
            <span data-capture="brightness"></span>
            <input type="checkbox" data-capture="debug-toggle" checked />
            <div data-capture="setup-help">Aim the camera… <button data-capture="start-alias">Start watching tonight</button></div>
            <div data-capture="night-help" class="hidden">If the screen keeps sleeping…</div>
            <dialog data-capture="preflight">
                <button data-capture="preflight-cancel">Not yet</button>
                <button data-capture="preflight-start">Start, I'm leaving</button>
            </dialog>
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
        stubs.uploaderEnd.mockResolvedValue({ ok: true, status: 200, reportUrl: 'https://bugtracker.test/surveillance/1/report' });
        stubs.localSinkEnd.mockResolvedValue({ ok: true, status: 200, reportUrl: 'https://bugtracker.test/watch/local-night-id/report' });
        stubs.localSinkOptions = null;

        mountPage();
        window.location.assign = vi.fn();
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true })));

        await bootCaptureApp();
    });

    afterEach(() => {
        removeTrackedWindowListeners();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    /** Click start, confirm the room checklist, and sit through the leave-the-room countdown. */
    async function startWatching() {
        el('start').click();
        el('preflight-start').click();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);
    }

    test('starting a night uploads the reference frame and begins watching', async () => {
        await startWatching();

        expect(stubs.cameraStart).toHaveBeenCalled();
        expect(stubs.uploaderStart).toHaveBeenCalled();
        expect(stubs.wakeAcquire).toHaveBeenCalled();
        expect(el('state').textContent).toBe('Watching');
        expect(el('brightness').textContent).toBe('120 / 255');

        expect(stubs.uploaderStoreReference).toHaveBeenCalledWith({
            blob: expect.any(Blob),
            frameWidth: 1280,
            frameHeight: 720,
            settings: expect.objectContaining({ procWidth: 320, diffThreshold: 20 }),
        });
        expect(stubs.localSinkStoreReference).not.toHaveBeenCalled();
    });

    /**
     * The stage holds one or the other: the sample room while the page is idle, the
     * camera once a stream is open. Leaving both in place puts an empty video strip
     * under the picture, and hiding both leaves the stage a collapsed black bar.
     */
    test('the sample room gives way to the camera for the night', async () => {
        expect(el('placeholder').classList.contains('hidden')).toBe(false);
        expect(el('video').classList.contains('hidden')).toBe(true);

        await startWatching();

        expect(el('placeholder').classList.contains('hidden')).toBe(true);
        expect(el('video').classList.contains('hidden')).toBe(false);
    });

    test('closing the camera check puts the sample room back', async () => {
        el('check').click();
        await settle();

        expect(el('placeholder').classList.contains('hidden')).toBe(true);

        el('check').click();
        await settle();

        expect(el('placeholder').classList.contains('hidden')).toBe(false);
        expect(el('video').classList.contains('hidden')).toBe(true);
    });

    // A refused camera prompt never opens a stream, so leaving the video in place
    // would swap the picture for a black strip and nothing would put it back.
    test('a refused camera leaves the sample room up', async () => {
        stubs.cameraStart.mockRejectedValue(new Error('Permission denied'));

        await startWatching();

        expect(el('placeholder').classList.contains('hidden')).toBe(false);
        expect(el('video').classList.contains('hidden')).toBe(true);
        expect(el('state').textContent).toBe('Error');
    });

    test('the camera check opens the preview without starting a night', async () => {
        el('check').click();
        await settle();

        expect(stubs.cameraStart).toHaveBeenCalled();
        expect(el('preflight').open).toBe(false);
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

        el('start').click();
        el('preflight-cancel').click();
        await settle();

        expect(stubs.cameraStop).not.toHaveBeenCalled();
        expect(el('check-label').textContent).toBe('Stop camera');
    });

    // It aims the device on an idle page and it is the way out of its own preview,
    // so it goes the moment start is pressed, not once watching has begun.
    test('the camera check makes way as soon as a night is started', async () => {
        el('start').click();
        el('preflight-start').click();
        await settle();

        expect(el('check').classList.contains('hidden')).toBe(true);

        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);

        expect(el('check').classList.contains('hidden')).toBe(true);
    });

    test('the camera check comes back when the night never got going', async () => {
        stubs.calibrate.mockResolvedValue({ meanLuminance: 2, tooDark: true, dim: true, diffThreshold: 14 });

        await startWatching();

        expect(el('state').textContent).toBe('Too dark');
        expect(el('check').classList.contains('hidden')).toBe(false);
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

        expect(el('preflight').open).toBe(true);
        expect(el('start').hasAttribute('disabled')).toBe(true);
        expect(stubs.cameraStart).not.toHaveBeenCalled();
    });

    test('backing out of the checklist starts nothing and leaves the button usable', async () => {
        el('start').click();
        el('preflight-cancel').click();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);

        expect(stubs.cameraStart).not.toHaveBeenCalled();
        expect(stubs.calibrate).not.toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
        expect(el('start').hasAttribute('disabled')).toBe(false);
        expect(el('state').textContent).toBe('');
    });

    // Escape is how a keyboard closes a dialog, and it hands back no value: that
    // has to read as backing out, not as a start.
    test('closing the checklist with Escape counts as backing out', async () => {
        el('start').click();
        el('preflight').close();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);

        expect(stubs.cameraStart).not.toHaveBeenCalled();
        expect(el('start').hasAttribute('disabled')).toBe(false);
    });

    test('nothing is measured until the user has had five seconds to leave', async () => {
        el('start').click();
        el('preflight-start').click();
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
        stubs.uploaderEnd.mockResolvedValue({ ok: false, status: 500, reportUrl: null });

        el('end').click();
        await settle();

        // Stranded on the page with an error banner: locking them out too would
        // leave no way off it at all.
        expect(nav().hasAttribute('inert')).toBe(false);
    });

    test('the setup advice makes way for the night-time reading once the night is under way', async () => {
        expect(el('setup-help').classList.contains('hidden')).toBe(false);
        expect(el('night-help').classList.contains('hidden')).toBe(true);

        await startWatching();

        expect(el('setup-help').classList.contains('hidden')).toBe(true);
        expect(el('night-help').classList.contains('hidden')).toBe(false);
    });

    // The hero's own start button forwards to the card's, so a page can offer the
    // start in its copy without a second start-up chain to keep in step.
    test('a start button in the page copy starts the night through the real one', async () => {
        el('start-alias').click();
        expect(el('preflight').open).toBe(true);
        el('preflight-start').click();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);

        expect(stubs.cameraStart).toHaveBeenCalledTimes(1);
        expect(el('end').classList.contains('hidden')).toBe(false);
    });

    test('a forwarded click is swallowed while the checklist is already open', async () => {
        el('start').click();
        await settle();

        // A second showModal() on an open dialog would throw; the disabled start
        // button is what keeps the forwarded click from reaching it.
        el('start-alias').click();
        el('preflight-start').click();
        await vi.advanceTimersByTimeAsync(LEAVE_ROOM_SECONDS * 1000);

        expect(stubs.cameraStart).toHaveBeenCalledTimes(1);
    });

    test('the end button only appears once watching', async () => {
        expect(el('end').classList.contains('hidden')).toBe(true);

        await startWatching();

        expect(el('start').classList.contains('hidden')).toBe(true);
        expect(el('check').classList.contains('hidden')).toBe(true);
        expect(el('end').classList.contains('hidden')).toBe(false);
    });

    test('the header light and start time only come on with the night itself', async () => {
        expect(el('idle-light').classList.contains('hidden')).toBe(false);
        expect(el('live-light').classList.contains('hidden')).toBe(true);
        expect(el('started').classList.contains('hidden')).toBe(true);

        await startWatching();

        expect(el('idle-light').classList.contains('hidden')).toBe(true);
        expect(el('live-light').classList.contains('hidden')).toBe(false);
        expect(el('started').classList.contains('hidden')).toBe(false);
        expect(el('started-at').textContent).toMatch(/\d{1,2}[:.]\d{2}/);
    });

    // A night that could not be ended stays on this page, and a light still
    // pulsing over a stopped camera would say it was still watching.
    test('the elapsed clock runs beside the state, and only while watching', async () => {
        expect(el('elapsed').classList.contains('hidden')).toBe(true);

        await startWatching();
        await vi.advanceTimersByTimeAsync(1000);

        expect(el('elapsed').classList.contains('hidden')).toBe(false);
        expect(el('elapsed').textContent).toMatch(/^\(\d{2}:\d{2}:\d{2}\)$/);
    });

    test('the header light goes out again when the night ends', async () => {
        stubs.uploaderEnd.mockResolvedValue({ ok: false, status: 500, reportUrl: null });

        await startWatching();
        el('end').click();
        await settle();

        expect(el('live-light').classList.contains('hidden')).toBe(true);
        expect(el('idle-light').classList.contains('hidden')).toBe(false);
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
        expect(el('check').classList.contains('hidden')).toBe(false);
        expect(el('state').textContent).toBe('Error');
    });

    test('a failed reference upload leaves the start button usable', async () => {
        stubs.uploaderStoreReference.mockRejectedValueOnce(new Error('Could not start the session (HTTP 422).'));

        await startWatching();

        expect(el('banner').textContent).toContain('422');
        expect(el('start').hasAttribute('disabled')).toBe(false);
    });

    test('ending the night reports it as kept, then goes to the report', async () => {
        await startWatching();

        el('end').click();
        await settle();

        const [payload] = stubs.uploaderEnd.mock.calls[0];
        expect(payload.aborted).toBe(false);
        expect(payload.endedAtOffsetMs).toBeGreaterThanOrEqual(0);

        expect(stubs.uploaderFlush).toHaveBeenCalledWith({ keepalive: true });
        expect(stubs.cameraStop).toHaveBeenCalled();
        expect(stubs.wakeRelease).toHaveBeenCalled();
        expect(window.location.assign).toHaveBeenCalledWith('https://bugtracker.test/surveillance/1/report');
    });

    test('the end button does nothing before a night has started', async () => {
        el('end').click();
        await settle();

        expect(stubs.uploaderEnd).not.toHaveBeenCalled();
    });

    test('a failed end keeps the user on the page and explains why', async () => {
        await startWatching();
        stubs.uploaderEnd.mockResolvedValue({ ok: false, status: 500, reportUrl: null });

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

    test('a person walking into shot is ignored rather than tracked as a swarm', async () => {
        stubs.grabProcessedFrame.mockImplementation(() => makeFrame(64, 64, 200));
        await startWatching();
        await vi.advanceTimersByTimeAsync(1000);

        // A 40×40 dark patch is 1600 changed pixels: past the production limit of
        // five roaches at maxArea, however many fragments a body breaks into.
        stubs.grabProcessedFrame.mockImplementation(() => paintRect(makeFrame(64, 64, 200), 0, 0, 40, 40, 100));
        await vi.advanceTimersByTimeAsync(1000);

        expect(el('state').textContent).toBe(LARGE_MOTION_MESSAGE);
        expect(stubs.uploaderEnqueue).not.toHaveBeenCalled();

        stubs.grabProcessedFrame.mockImplementation(() => makeFrame(64, 64, 200));
        await vi.advanceTimersByTimeAsync(1000);

        expect(el('state').textContent).toBe('Watching');
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
    describe('as a guest, with no account', () => {
        beforeEach(async () => {
            removeTrackedWindowListeners();
            mountPage(LOCAL_CONFIG);
            await bootCaptureApp();
        });

        test('the night is kept in this browser and nothing is sent to the server', async () => {
            await startWatching();

            expect(stubs.openNightStore).toHaveBeenCalled();
            expect(stubs.localSinkOptions.reportUrlTemplate).toBe(LOCAL_CONFIG.routes.report);
            expect(stubs.localSinkStoreReference).toHaveBeenCalledWith(expect.objectContaining({ frameWidth: 1280, frameHeight: 720 }));
            expect(stubs.localSinkStart).toHaveBeenCalled();
            expect(stubs.uploaderStoreReference).not.toHaveBeenCalled();
            expect(fetch).not.toHaveBeenCalled();
            expect(el('state').textContent).toBe('Watching');
        });

        test('the status line says the reference frame is being saved, not uploaded', async () => {
            let stateWhileStoring = null;
            stubs.localSinkStoreReference.mockImplementationOnce(async () => {
                stateWhileStoring = el('state').textContent;
            });

            await startWatching();

            expect(stateWhileStoring).toBe('Saving reference frame…');
        });

        test('ending the night goes to the local report', async () => {
            await startWatching();

            el('end').click();
            await settle();

            expect(stubs.localSinkFlush).toHaveBeenCalledWith({ keepalive: true });
            expect(stubs.localSinkEnd.mock.calls[0][0].aborted).toBe(false);
            expect(window.location.assign).toHaveBeenCalledWith('https://bugtracker.test/watch/local-night-id/report');
            expect(fetch).not.toHaveBeenCalled();
        });
    });
});
