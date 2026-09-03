import { Camera } from './camera.js';
import { calibrate } from './brightness.js';
import {
    buildReferenceForm,
    calibrationOutcome,
    CAMERA_CHECK_MESSAGE,
    cameraCheckLabel,
    countdownMessage,
    formatClock,
    LEAVE_ROOM_SECONDS,
    overlayBoxes,
    PREFLIGHT_MESSAGE,
    wakeLockMessage,
} from './captureLogic.js';
import { Detector, DEFAULT_PARAMS } from './detector.js';
import { Tracker } from './tracker.js';
import { Uploader } from './uploader.js';
import { WakeLock } from './wakeLock.js';

const root = document.getElementById('capture-app');

if (root !== null) {
    initCaptureApp(root);
}

function initCaptureApp(root) {
    const config = JSON.parse(root.dataset.config);
    const el = (name) => root.querySelector(`[data-capture="${name}"]`);

    const ui = {
        video: el('video'),
        overlay: el('overlay'),
        startButton: el('start'),
        checkButton: el('check'),
        checkLabel: el('check-label'),
        endButton: el('end'),
        abortButton: el('abort'),
        banner: el('banner'),
        state: el('state'),
        elapsed: el('elapsed'),
        trackCount: el('track-count'),
        liveCount: el('live-count'),
        queueDepth: el('queue-depth'),
        brightness: el('brightness'),
        debugToggle: el('debug-toggle'),
        setupHelp: el('setup-help'),
    };

    const app = {
        camera: new Camera(ui.video, DEFAULT_PARAMS.procWidth),
        detector: null,
        tracker: null,
        uploader: null,
        wakeLock: new WakeLock(() => showBanner(wakeLockMessage(navigator.userAgent))),
        running: false,
        previewing: false,
        sessionStartTime: null,
        loopTimer: null,
    };

    ui.startButton.addEventListener('click', () => startNight().catch((error) => {
        // A refused camera prompt or a failed upload must leave the button usable,
        // otherwise the only way to try again is reloading the page.
        ui.startButton.removeAttribute('disabled');
        setState('Error');
        showBanner(String(error));
    }));
    ui.checkButton.addEventListener('click', () => toggleCameraCheck().catch((error) => {
        // Same reasoning as the start button: a refused prompt must not leave the
        // page stuck believing a preview is open.
        stopCameraCheck();
        setState('Error');
        showBanner(String(error));
    }));
    ui.endButton.addEventListener('click', () => endNight(false));
    ui.abortButton.addEventListener('click', () => {
        // Discarding is for a night set up wrong — a bad angle, a light left on —
        // whose sightings would otherwise skew the trend and the entry point map.
        if (window.confirm('Discard this night? It stays in your list, with its report, but is left out of trends and entry points.')) {
            endNight(true);
        }
    });
    // The back button and closing the tab reach past the locked chrome, and either
    // one ends the night for good. Browsers word this prompt themselves; all we can
    // do is ask for it. Ending or discarding clears app.running first, so the trip
    // to the report is never interrupted.
    window.addEventListener('beforeunload', (event) => {
        if (app.running) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    window.addEventListener('pagehide', () => {
        if (app.running) {
            app.uploader.flush({ keepalive: true });
        }
    });

    /**
     * Lock the app chrome while a night is recording. Leaving this page ends the
     * night, so a stray tap on a sidebar link would throw away hours of watching
     * with nothing asking first. `inert` takes the links out of the tab order too,
     * which pointer-events alone would not; the fade says the lock is deliberate
     * rather than the page having broken.
     */
    function setNavigationLocked(locked) {
        for (const region of document.querySelectorAll('[data-app-nav]')) {
            region.toggleAttribute('inert', locked);
            region.classList.toggle('opacity-40', locked);
        }
    }

    function showBanner(message) {
        ui.banner.textContent = message;
        ui.banner.classList.remove('hidden');
    }

    function hideBanner() {
        ui.banner.classList.add('hidden');
    }

    /**
     * Open the preview on its own so the device can be aimed before a night is
     * committed to. Nothing is measured, uploaded or recorded here — it is the
     * same camera the night uses, held open until the user is happy or presses
     * start.
     */
    async function toggleCameraCheck() {
        if (app.previewing) {
            stopCameraCheck();
            setState('Idle');

            return;
        }

        setState('Starting camera…');

        await app.camera.start();

        app.previewing = true;
        ui.checkLabel.textContent = cameraCheckLabel(true);
        setState(CAMERA_CHECK_MESSAGE);
    }

    /** Close the preview stream, leaving the status line to the caller. */
    function stopCameraCheck() {
        if (!app.previewing) {
            return;
        }

        app.camera.stop();
        app.previewing = false;
        ui.checkLabel.textContent = cameraCheckLabel(false);
    }

    async function startNight() {
        // Asked before the camera opens: the light has to be on before calibration
        // measures the scene, and a user who backs out should not have been filmed.
        if (!window.confirm(PREFLIGHT_MESSAGE)) {
            return;
        }

        // A preview holds a stream of its own. Close it before the night opens the
        // camera, or getUserMedia hands back a second one and the first keeps the
        // device — and its recording light — running for the rest of the night.
        stopCameraCheck();

        ui.startButton.setAttribute('disabled', 'disabled');
        setState('Starting camera…');

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
            app.camera.stop();

            return;
        }

        const settings = { ...DEFAULT_PARAMS, diffThreshold: calibration.diffThreshold };

        setState('Uploading reference frame…');
        await uploadReference(settings);

        app.sessionStartTime = Date.now();
        app.detector = new Detector(settings);
        app.uploader = new Uploader({
            routes: config.routes,
            csrfToken: config.csrfToken,
            onStatus: (status) => {
                if (status.queueDepth !== undefined) {
                    ui.queueDepth.textContent = status.queueDepth;
                }
                if (status.authLost) {
                    showBanner('Your login session expired — log in again in another tab, then reload this page. Detected tracks are held in memory.');
                }
            },
        });
        app.tracker = new Tracker({
            scale: app.camera.scale,
            sessionStartTime: app.sessionStartTime,
            captureCrop: (x, y) => app.camera.captureCropBase64(x, y),
            onTrackClosed: (track) => {
                app.uploader.enqueue(track);
                ui.trackCount.textContent = app.tracker.closedCount;
            },
        });

        app.uploader.start();
        await app.wakeLock.acquire();

        app.running = true;
        setNavigationLocked(true);
        ui.setupHelp.classList.add('hidden');
        ui.startButton.classList.add('hidden');
        ui.checkButton.classList.add('hidden');
        ui.endButton.classList.remove('hidden');
        ui.abortButton.classList.remove('hidden');
        setState('Watching');

        const intervalMs = 1000 / settings.processFps;
        app.loopTimer = setInterval(processFrame, intervalMs);
        setInterval(updateElapsed, 1000);
    }

    /**
     * Hold the camera open but idle while the user walks out, counting down on the
     * status line. Nothing is measured until this finishes, so the reference photo,
     * the background model and the noise floor all describe an empty room.
     */
    async function countdownToLeave() {
        for (let secondsLeft = LEAVE_ROOM_SECONDS; secondsLeft > 0; secondsLeft--) {
            setState(countdownMessage(secondsLeft));

            await new Promise((resolve) => setTimeout(resolve, 1000));
        }
    }

    async function uploadReference(settings) {
        const form = buildReferenceForm({
            blob: await app.camera.captureReferenceJpeg(),
            frameWidth: app.camera.frameWidth,
            frameHeight: app.camera.frameHeight,
            settings,
        });

        const response = await fetch(config.routes.reference, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
            body: form,
        });

        if (!response.ok) {
            throw new Error(`Could not start the session (HTTP ${response.status}).`);
        }
    }

    function processFrame() {
        if (!app.running) {
            return;
        }

        const frame = app.camera.grabProcessedFrame();
        const blobs = app.detector.detect(frame);
        app.tracker.update(blobs);

        ui.liveCount.textContent = app.tracker.active.length;
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
        if (!app.running) {
            return;
        }

        ui.elapsed.textContent = formatClock(Date.now() - app.sessionStartTime);
    }

    async function endNight(aborted) {
        if (!app.running) {
            return;
        }

        app.running = false;
        setNavigationLocked(false);
        clearInterval(app.loopTimer);
        setState('Finishing…');

        app.tracker.flush();
        app.uploader.stop();
        await app.uploader.flush({ keepalive: true });
        app.camera.stop();
        await app.wakeLock.release();

        const response = await app.uploader.post(config.routes.end, {
            ended_at_offset_ms: Date.now() - app.sessionStartTime,
            aborted,
        });

        if (response.ok) {
            const { report_url: reportUrl } = await response.json();
            window.location.assign(reportUrl);
        } else {
            showBanner(`Could not end the session (HTTP ${response.status}). Your tracks are saved — retry from the sessions page.`);
            setState('Error');
        }
    }

    function setState(text) {
        ui.state.textContent = text;
    }
}
