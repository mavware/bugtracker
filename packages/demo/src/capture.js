// The demo's capture page: the library's camera, detector and tracker wired to
// the markup in index.html, with every night kept in this browser's own store.
// This is the reference for wiring @mavware/bug-surveillance into a page of
// your own: the library decides, this file only plumbs.
import {
    calibrate,
    calibrationOutcome,
    Camera,
    CAMERA_CHECK_MESSAGE,
    cameraCheckLabel,
    countdownMessage,
    DEFAULT_PARAMS,
    Detector,
    formatClock,
    LEAVE_ROOM_SECONDS,
    LocalNightSink,
    openNightStore,
    overlayBoxes,
    PREFLIGHT_MESSAGE,
    Tracker,
    VOLATILE_STORE_MESSAGE,
    WakeLock,
    wakeLockMessage,
    watchingState,
} from '@mavware/bug-surveillance';
import { REPORT_URL_TEMPLATE } from './links.js';

const root = document.getElementById('capture-app');

if (root !== null) {
    initCaptureApp(root);
}

function initCaptureApp(root) {
    const el = (name) => root.querySelector(`[data-capture="${name}"]`);

    const ui = {
        video: el('video'),
        overlay: el('overlay'),
        placeholder: el('placeholder'),
        startButton: el('start'),
        checkButton: el('check'),
        checkLabel: el('check-label'),
        endButton: el('end'),
        banner: el('banner'),
        state: el('state'),
        elapsed: el('elapsed'),
        trackCount: el('track-count'),
        liveCount: el('live-count'),
        queueDepth: el('queue-depth'),
        brightness: el('brightness'),
        debugToggle: el('debug-toggle'),
        setupHelp: el('setup-help'),
        idleLight: el('idle-light'),
        liveLight: el('live-light'),
        started: el('started'),
        startedAt: el('started-at'),
    };

    const app = {
        camera: new Camera(ui.video, DEFAULT_PARAMS.procWidth),
        detector: null,
        tracker: null,
        sink: null,
        wakeLock: new WakeLock(() => showBanner(wakeLockMessage(navigator.userAgent))),
        running: false,
        previewing: false,
        sessionStartTime: null,
        loopTimer: null,
    };

    ui.startButton.addEventListener('click', () => startNight().catch((error) => {
        // A refused camera prompt must leave the buttons usable, otherwise the
        // only way to try again is reloading the page.
        ui.startButton.removeAttribute('disabled');
        showCameraCheck(true);
        showCameraStage(false);
        setState('Error');
        showBanner(String(error));
    }));
    ui.checkButton.addEventListener('click', () => toggleCameraCheck().catch((error) => {
        stopCameraCheck();
        showCameraStage(false);
        setState('Error');
        showBanner(String(error));
    }));
    ui.endButton.addEventListener('click', () => endNight());
    // Closing the tab ends the night for good. Browsers word this prompt
    // themselves; all we can do is ask for it.
    window.addEventListener('beforeunload', (event) => {
        if (app.running) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.addEventListener('pagehide', () => {
        if (app.running) {
            app.sink.flush();
        }
    });

    /** Lock the page chrome while a night records; a stray tap on a link would end it. */
    function setNavigationLocked(locked) {
        for (const region of document.querySelectorAll('[data-app-nav]')) {
            region.toggleAttribute('inert', locked);
        }
    }

    function showBanner(message) {
        ui.banner.textContent = message;
        ui.banner.classList.remove('hidden');
    }

    function hideBanner() {
        ui.banner.classList.add('hidden');
    }

    /** Open the preview on its own so the device can be aimed before a night is committed to. */
    async function toggleCameraCheck() {
        if (app.previewing) {
            stopCameraCheck();
            setState('Idle');

            return;
        }

        setState('Starting camera…');
        showCameraStage(true);
        await app.camera.start();

        app.previewing = true;
        ui.checkLabel.textContent = cameraCheckLabel(true);
        setState(CAMERA_CHECK_MESSAGE);
    }

    /**
     * The stage holds either the sample room or the camera, never both: the video
     * is what gives the stage its height, and an element with no stream collapses
     * to a strip. Called around every camera start and stop that returns the page
     * to idle — but not after a night ends, where the frozen last frame is the
     * honest thing to leave on screen behind a failed end.
     */
    function showCameraStage(live) {
        ui.placeholder.classList.toggle('hidden', live);
        ui.video.classList.toggle('hidden', !live);
    }

    /**
     * The camera check belongs to an idle page: it aims the device before a night
     * is committed to, and it is the way back out of the preview it opens. From
     * the moment start is pressed it goes, until the page is idle again.
     */
    function showCameraCheck(visible) {
        ui.checkButton.classList.toggle('hidden', !visible);
    }

    function stopCameraCheck() {
        if (!app.previewing) {
            return;
        }

        app.camera.stop();
        app.previewing = false;
        showCameraStage(false);
        ui.checkLabel.textContent = cameraCheckLabel(false);
    }

    // The order is load-bearing: checklist, camera, countdown, then measure.
    // The countdown comes before calibration and the reference photo so all of
    // them describe an empty room rather than the person walking out of it.
    async function startNight() {
        if (!window.confirm(PREFLIGHT_MESSAGE)) {
            return;
        }

        stopCameraCheck();

        ui.startButton.setAttribute('disabled', 'disabled');
        showCameraCheck(false);
        setState('Starting camera…');
        showCameraStage(true);
        await app.camera.start();

        await countdownToLeave();

        setState('Calibrating (3s)…');
        const calibration = await calibrate(app.camera);
        ui.brightness.textContent = `${Math.round(calibration.meanLuminance)} / 255`;

        const outcome = calibrationOutcome(calibration);

        if (outcome.banner !== null) {
            showBanner(outcome.banner);
        } else {
            hideBanner();
        }

        if (outcome.blocked) {
            setState('Too dark');
            ui.startButton.removeAttribute('disabled');
            showCameraCheck(true);
            app.camera.stop();
            showCameraStage(false);

            return;
        }

        const settings = { ...DEFAULT_PARAMS, diffThreshold: calibration.diffThreshold };
        const store = await openNightStore();

        if (store.volatile) {
            showBanner(VOLATILE_STORE_MESSAGE);
        }

        app.sink = new LocalNightSink({
            store,
            reportUrlTemplate: REPORT_URL_TEMPLATE,
            onStatus: (status) => {
                if (status.queueDepth !== undefined) {
                    ui.queueDepth.textContent = String(status.queueDepth);
                }
            },
        });

        setState('Saving reference frame…');
        await app.sink.storeReference({
            blob: await app.camera.captureReferenceJpeg(),
            frameWidth: app.camera.frameWidth,
            frameHeight: app.camera.frameHeight,
            settings,
        });

        app.sessionStartTime = Date.now();
        app.detector = new Detector(settings);
        app.tracker = new Tracker({
            scale: app.camera.scale,
            sessionStartTime: app.sessionStartTime,
            captureCrop: (x, y) => app.camera.captureCropBase64(x, y),
            onTrackClosed: (track) => {
                app.sink.enqueue(track);
                ui.trackCount.textContent = String(app.tracker.closedCount);
            },
        });

        app.sink.start();
        await app.wakeLock.acquire();

        app.running = true;
        setNavigationLocked(true);
        showRecording(true);
        ui.setupHelp.classList.add('hidden');
        ui.startButton.classList.add('hidden');
        ui.endButton.classList.remove('hidden');
        setState(watchingState(false));

        app.loopTimer = setInterval(processFrame, 1000 / settings.processFps);
        setInterval(updateElapsed, 1000);
    }

    async function countdownToLeave() {
        for (let secondsLeft = LEAVE_ROOM_SECONDS; secondsLeft > 0; secondsLeft--) {
            setState(countdownMessage(secondsLeft));
            await new Promise((resolve) => setTimeout(resolve, 1000));
        }
    }

    function processFrame() {
        if (!app.running) {
            return;
        }

        const blobs = app.detector.detect(app.camera.grabProcessedFrame());
        app.tracker.update(blobs);

        ui.liveCount.textContent = String(app.tracker.active.length);
        setState(watchingState(app.detector.largeMotion));
        drawOverlay(blobs);
    }

    function drawOverlay(blobs) {
        const canvas = ui.overlay;
        const ctx = canvas.getContext('2d');

        if (canvas.width !== ui.video.clientWidth) {
            canvas.width = ui.video.clientWidth;
            canvas.height = ui.video.clientHeight;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!ui.debugToggle.checked) {
            return;
        }

        // A dropped frame gets a red border instead of boxes: the person in
        // shot is being ignored, not missed.
        if (app.detector.largeMotion) {
            ctx.strokeStyle = '#f87171';
            ctx.lineWidth = 6;
            ctx.strokeRect(0, 0, canvas.width, canvas.height);

            return;
        }

        ctx.strokeStyle = '#4ade80';
        ctx.lineWidth = 2;

        const boxes = overlayBoxes(blobs, {
            canvasWidth: canvas.width,
            canvasHeight: canvas.height,
            procWidth: app.camera.procCanvas.width,
            procHeight: app.camera.procCanvas.height,
        });

        for (const box of boxes) {
            ctx.strokeRect(box.x, box.y, box.width, box.height);
        }
    }

    function updateElapsed() {
        if (app.running) {
            // Parenthesised because it reads as part of the state beside it: "Tracking (01:12:40)".
            ui.elapsed.textContent = `(${formatClock(Date.now() - app.sessionStartTime)})`;
        }
    }

    async function endNight() {
        if (!app.running) {
            return;
        }

        app.running = false;
        setNavigationLocked(false);
        showRecording(false);
        clearInterval(app.loopTimer);
        setState('Finishing…');

        app.tracker.flush();
        app.sink.stop();
        await app.sink.flush();
        app.camera.stop();
        await app.wakeLock.release();

        // Never aborted from here: a night is discarded from its report, where the
        // trails it caught are there to judge the setup by.
        const result = await app.sink.end({
            endedAtOffsetMs: Date.now() - app.sessionStartTime,
            aborted: false,
        });

        window.location.assign(result.reportUrl);
    }

    function setState(text) {
        ui.state.textContent = text;
    }

    /** The header's recording light, and the clock time the night began beside it. */
    function showRecording(recording) {
        ui.idleLight.classList.toggle('hidden', recording);
        ui.liveLight.classList.toggle('hidden', !recording);
        ui.started.classList.toggle('hidden', !recording);
        ui.elapsed.classList.toggle('hidden', !recording);

        if (recording) {
            ui.startedAt.textContent = new Date(app.sessionStartTime)
                .toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }
}
