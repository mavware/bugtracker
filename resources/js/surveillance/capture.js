import { Camera } from './camera.js';
import { calibrate } from './brightness.js';
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
        endButton: el('end'),
        banner: el('banner'),
        state: el('state'),
        elapsed: el('elapsed'),
        trackCount: el('track-count'),
        liveCount: el('live-count'),
        queueDepth: el('queue-depth'),
        brightness: el('brightness'),
        debugToggle: el('debug-toggle'),
    };

    const app = {
        camera: new Camera(ui.video, DEFAULT_PARAMS.procWidth),
        detector: null,
        tracker: null,
        uploader: null,
        wakeLock: new WakeLock(() => showBanner('Screen wake lock is unavailable — disable auto-lock manually so the device stays on.')),
        running: false,
        sessionStartTime: null,
        loopTimer: null,
    };

    ui.startButton.addEventListener('click', () => startNight().catch((error) => showBanner(String(error))));
    ui.endButton.addEventListener('click', () => endNight(false));
    window.addEventListener('pagehide', () => {
        if (app.running) {
            app.uploader.flush({ keepalive: true });
        }
    });

    function showBanner(message) {
        ui.banner.textContent = message;
        ui.banner.classList.remove('hidden');
    }

    function hideBanner() {
        ui.banner.classList.add('hidden');
    }

    async function startNight() {
        ui.startButton.setAttribute('disabled', 'disabled');
        setState('Starting camera…');

        await app.camera.start();

        setState('Calibrating (3s)…');
        const calibration = await calibrate(app.camera);
        ui.brightness.textContent = `${Math.round(calibration.meanLuminance)} / 255`;

        if (calibration.tooDark) {
            setState('Too dark');
            ui.startButton.removeAttribute('disabled');
            showBanner('The scene is pitch black — the camera cannot see anything. Add a nightlight or dim lamp, then start again.');
            app.camera.stop();

            return;
        }

        if (calibration.dim) {
            showBanner('The scene is very dim. Detection will run, but a small extra light source would improve it.');
        } else {
            hideBanner();
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
        ui.startButton.classList.add('hidden');
        ui.endButton.classList.remove('hidden');
        setState('Watching');

        const intervalMs = 1000 / settings.processFps;
        app.loopTimer = setInterval(processFrame, intervalMs);
        setInterval(updateElapsed, 1000);
    }

    async function uploadReference(settings) {
        const blob = await app.camera.captureReferenceJpeg();
        const form = new FormData();
        form.append('image', blob, 'reference.jpg');
        form.append('frame_width', app.camera.frameWidth);
        form.append('frame_height', app.camera.frameHeight);

        for (const [key, value] of Object.entries(settings)) {
            form.append(`settings[${key}]`, value);
        }

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

        const scaleX = canvas.width / app.camera.procCanvas.width;
        const scaleY = canvas.height / app.camera.procCanvas.height;

        ctx.strokeStyle = '#4ade80';
        ctx.lineWidth = 2;

        for (const blob of blobs) {
            ctx.strokeRect(
                (blob.box.x - 2) * scaleX,
                (blob.box.y - 2) * scaleY,
                (blob.box.width + 4) * scaleX,
                (blob.box.height + 4) * scaleY,
            );
        }
    }

    function updateElapsed() {
        if (!app.running) {
            return;
        }

        const totalSeconds = Math.floor((Date.now() - app.sessionStartTime) / 1000);
        const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        ui.elapsed.textContent = `${hours}:${minutes}:${seconds}`;
    }

    async function endNight(aborted) {
        if (!app.running) {
            return;
        }

        app.running = false;
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
