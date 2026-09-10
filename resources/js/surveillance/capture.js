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
    Tracker,
    WakeLock,
    wakeLockMessage,
    watchingState,
} from '@mavware/bug-surveillance';
import { AUTH_LOST_MESSAGE, autoEndAfterMs, captureMode, referenceStoreState } from './captureLogic.js';
import { Uploader } from './uploader.js';

const root = document.getElementById('capture-app');

if (root !== null) {
    initCaptureApp(root);
}

function initCaptureApp(root) {
    const config = JSON.parse(root.dataset.config);
    const el = (name) => root.querySelector(`[data-capture="${name}"]`);

    // The button's resting name, read back from the page so the copy lives in one place.
    const startLabel = el('start-label').textContent.trim();

    const ui = {
        video: el('video'),
        overlay: el('overlay'),
        placeholder: el('placeholder'),
        startButton: el('start'),
        startLabel: el('start-label'),
        startPlay: el('start-play'),
        startSpinner: el('start-spinner'),
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
        nightHelp: el('night-help'),
        preflight: el('preflight'),
        preflightStart: el('preflight-start'),
        preflightCancel: el('preflight-cancel'),
        idleLight: el('idle-light'),
        liveLight: el('live-light'),
        started: el('started'),
        startedAt: el('started-at'),
        autoEnd: el('auto-end'),
        autoEndHours: el('auto-end-hours'),
        autoEndFields: el('auto-end-fields'),
        // Said twice, in the header and in the night-time reading, so both are
        // lists: every note is shown and hidden together, every clock written alike.
        autoEndNotes: [...root.querySelectorAll('[data-capture="auto-end-note"]')],
        autoEndClocks: [...root.querySelectorAll('[data-capture="auto-end-at"]')],
    };

    const app = {
        camera: new Camera(ui.video, DEFAULT_PARAMS.procWidth),
        detector: null,
        tracker: null,
        // Where the night goes: the server for a logged-in user, this browser's
        // own store for a guest. Same calls either way; see buildSink().
        sink: null,
        wakeLock: new WakeLock(() => showBanner(wakeLockMessage(navigator.userAgent))),
        running: false,
        previewing: false,
        sessionStartTime: null,
        // The clock time the night ends itself, or null for one that runs until
        // End night is pressed. Checked by the once-a-second clock rather than a
        // setTimeout of its own: a backgrounded tab clamps and skips long timers,
        // but cannot miss a deadline that every tick compares against.
        autoEndAt: null,
        loopTimer: null,
    };

    ui.startButton.addEventListener('click', () => startNight().catch((error) => {
        // A refused camera prompt or a failed upload must leave the buttons usable,
        // otherwise the only way to try again is reloading the page.
        setStartBusy(false);
        showNightReading(false);
        showCameraCheck(true);
        showCameraStage(false);
        setState('Error');
        showBanner(String(error));
    }));
    // A page may put a second start button in its own copy. It forwards to the
    // real one, so the checklist, the camera and the countdown run once, and a
    // disabled start button swallows the forwarded click the way it does its own.
    el('start-alias')?.addEventListener('click', () => ui.startButton.click());
    ui.checkButton.addEventListener('click', () => toggleCameraCheck().catch((error) => {
        // Same reasoning as the start button: a refused prompt must not leave the
        // page stuck believing a preview is open.
        stopCameraCheck();
        showNightReading(false);
        showCameraStage(false);
        setState('Error');
        showBanner(String(error));
    }));
    ui.endButton.addEventListener('click', () => endNight());
    ui.preflightCancel.addEventListener('click', () => ui.preflight.close(''));
    // The hours field only means anything while the box is ticked, so it only shows then.
    ui.autoEnd.addEventListener('change', () => ui.autoEndFields.classList.toggle('hidden', !ui.autoEnd.checked));
    // The back button and closing the tab reach past the locked chrome, and either
    // one ends the night for good. Browsers word this prompt themselves; all we can
    // do is ask for it. Ending clears app.running first, so the trip to the report
    // is never interrupted.
    window.addEventListener('beforeunload', (event) => {
        if (app.running) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    window.addEventListener('pagehide', () => {
        if (app.running) {
            app.sink.flush({ keepalive: true });
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
            showNightReading(false);
            setState('Idle');

            return;
        }

        showNightReading(true);
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
     * to a strip. Called around every camera.start()/stop() that returns the page
     * to idle — but not after a night ends, where the frozen last frame is the
     * honest thing to leave on screen behind a failed end.
     */
    function showCameraStage(live) {
        ui.placeholder.classList.toggle('hidden', live);
        ui.video.classList.toggle('hidden', !live);
    }

    /**
     * The side column's two faces. The setup reading — aiming advice, or the
     * hero's copy — goes whenever the status leaves Idle, whether for a camera
     * check or a confirmed checklist, and the night-time reading takes its place.
     * Every path back to Idle — a closed check, a refused camera, a too-dark
     * room, a failed start — puts the setup reading back.
     */
    function showNightReading(night) {
        ui.setupHelp.classList.toggle('hidden', night);
        ui.nightHelp.classList.toggle('hidden', !night);
    }

    /**
     * The camera check belongs to an idle page: it is the way to aim the device
     * before committing to a night, and the way back out of the preview it opens.
     * From the moment start is pressed it has no place, so it goes until the page
     * is idle again.
     */
    function showCameraCheck(visible) {
        ui.checkButton.classList.toggle('hidden', !visible);
    }

    /** Close the preview stream, leaving the status line to the caller. */
    function stopCameraCheck() {
        if (!app.previewing) {
            return;
        }

        app.camera.stop();
        app.previewing = false;
        showCameraStage(false);
        ui.checkLabel.textContent = cameraCheckLabel(false);
    }

    /**
     * Put the room checklist in front of the user and wait for their answer. The
     * dialog is the page's; Start closes it with a value, Cancel and Escape close
     * it empty, and the close event is the one place both are read.
     */
    function askPreflight() {
        return new Promise((resolve) => {
            const onStart = () => ui.preflight.close('start');
            const onClose = () => {
                ui.preflightStart.removeEventListener('click', onStart);
                resolve(ui.preflight.returnValue === 'start');
            };

            ui.preflightStart.addEventListener('click', onStart);
            ui.preflight.addEventListener('close', onClose, { once: true });
            ui.preflight.returnValue = '';
            ui.preflight.showModal();
        });
    }

    async function startNight() {
        // Asked before the camera opens: the light has to be on before calibration
        // measures the scene, and a user who backs out should not have been filmed.
        // The button is held disabled for the asking, so a second press — or a
        // forwarded one — cannot open the checklist twice.
        setStartBusy(true);

        if (!(await askPreflight())) {
            setStartBusy(false);

            return;
        }

        // A preview holds a stream of its own. Close it before the night opens the
        // camera, or getUserMedia hands back a second one and the first keeps the
        // device — and its recording light — running for the rest of the night.
        stopCameraCheck();

        showCameraCheck(false);
        showNightReading(true);
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
            setStartBusy(false);
            showNightReading(false);
            showCameraCheck(true);
            app.camera.stop();
            showCameraStage(false);

            return;
        }

        const settings = { ...DEFAULT_PARAMS, diffThreshold: calibration.diffThreshold };

        app.sink = await buildSink();
        setState(referenceStoreState(captureMode(config)));
        await app.sink.storeReference({
            blob: await app.camera.captureReferenceJpeg(),
            frameWidth: app.camera.frameWidth,
            frameHeight: app.camera.frameHeight,
            settings,
        });

        app.sessionStartTime = Date.now();

        const autoEndMs = autoEndAfterMs({ enabled: ui.autoEnd.checked, hours: ui.autoEndHours.value });
        app.autoEndAt = autoEndMs === null ? null : app.sessionStartTime + autoEndMs;
        app.detector = new Detector(settings);
        app.tracker = new Tracker({
            scale: app.camera.scale,
            sessionStartTime: app.sessionStartTime,
            captureCrop: (x, y) => app.camera.captureCropBase64(x, y),
            onTrackClosed: (track) => {
                app.sink.enqueue(track);
                ui.trackCount.textContent = app.tracker.closedCount;
            },
        });

        app.sink.start();
        await app.wakeLock.acquire();

        app.running = true;
        setNavigationLocked(true);
        showRecording(true);
        ui.startButton.classList.add('hidden');
        ui.endButton.classList.remove('hidden');
        setState(watchingState(false));

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

    /**
     * The one place the page cares whether it is a guest's night or a
     * logged-in one. Both sinks answer the same calls from here on.
     */
    async function buildSink() {
        const onStatus = (status) => {
            if (status.queueDepth !== undefined) {
                ui.queueDepth.textContent = status.queueDepth;
            }
            if (status.authLost) {
                showBanner(AUTH_LOST_MESSAGE);
            }
        };

        if (captureMode(config) === 'local') {
            return new LocalNightSink({
                store: await openNightStore(),
                reportUrlTemplate: config.routes.report,
                onStatus,
            });
        }

        return new Uploader({ routes: config.routes, csrfToken: config.csrfToken, onStatus });
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

        // A dropped frame draws no boxes: the person standing in shot is being
        // ignored, not missed, and saying so on screen only made the page jump.
        if (app.detector.largeMotion) {
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

        if (app.autoEndAt !== null && Date.now() >= app.autoEndAt) {
            endNight();

            return;
        }

        // Parenthesised because it reads as part of the state beside it: "Tracking (01:12:40)".
        ui.elapsed.textContent = `(${formatClock(Date.now() - app.sessionStartTime)})`;
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
        await app.sink.flush({ keepalive: true });
        app.camera.stop();
        await app.wakeLock.release();

        // Never aborted from here: a night is discarded from its report, where the
        // trails it caught are there to judge the setup by.
        const result = await app.sink.end({
            endedAtOffsetMs: Date.now() - app.sessionStartTime,
            aborted: false,
        });

        if (result.ok) {
            window.location.assign(result.reportUrl);
        } else {
            showBanner(`Could not end the session (HTTP ${result.status}). Your tracks are saved — retry from the sessions page.`);
            setState('Error');
        }
    }

    /**
     * The status line, and — for as long as the start button is disabled — the
     * button's own label, so the button says what it is busy with rather than
     * sitting greyed out under a name that is no longer true.
     */
    function clockTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function setState(text) {
        ui.state.textContent = text;

        if (ui.startButton.hasAttribute('disabled')) {
            ui.startLabel.textContent = text;
        }
    }

    /**
     * Disable the start button and turn its play icon into a spinner, or give it
     * back: the label is written by setState while busy and restored here.
     */
    function setStartBusy(busy) {
        ui.startButton.toggleAttribute('disabled', busy);
        ui.startPlay.classList.toggle('hidden', busy);
        ui.startSpinner.classList.toggle('hidden', !busy);

        if (!busy) {
            ui.startLabel.textContent = startLabel;
        }
    }

    /**
     * The header's recording light, and the clock time the night began beside it.
     * Ending the night usually leaves for the report, but a failed end stays here,
     * and a light still pulsing on a finished night would be a lie.
     */
    function showRecording(recording) {
        ui.idleLight.classList.toggle('hidden', recording);
        ui.liveLight.classList.toggle('hidden', !recording);
        ui.started.classList.toggle('hidden', !recording);
        ui.elapsed.classList.toggle('hidden', !recording);
        for (const note of ui.autoEndNotes) {
            note.classList.toggle('hidden', !recording || app.autoEndAt === null);
        }

        if (recording) {
            ui.startedAt.textContent = clockTime(app.sessionStartTime);

            for (const clock of ui.autoEndClocks) {
                clock.textContent = app.autoEndAt === null ? '' : clockTime(app.autoEndAt);
            }
        }
    }
}
