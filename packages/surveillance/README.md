# @mavware/bug-surveillance

Point a phone or laptop camera at a room, leave it running all night, and get
back every time something walked through the frame: when, where it came in,
where it left, and a snapshot of each sighting.

Detection, tracking and reporting all run in the browser. There is no server,
no upload and no machine learning model to download. A night can be kept in the
browser's own IndexedDB store, so the library works with no backend at all.

Live demo: <https://mavware.github.io/bugtracker/>

## Install

```bash
npm install @mavware/bug-surveillance
```

TypeScript declarations are included. This is a browser library, so a consumer's
`tsconfig.json` needs `"lib": ["ES2022", "DOM"]`.

## How it fits together

Four pieces, which you wire into your own page:

- **`Camera`** owns the video stream and two canvases, one at full resolution
  for reference photos and snapshots, one downscaled for per-frame work.
- **`Detector`** keeps a running-average background and reports the dark,
  bug-sized blobs that differ from it. A frame holding something far larger than
  a bug, such as a person or a pet, is dropped whole rather than reported as a
  swarm.
- **`Tracker`** joins those blobs into paths across frames and hands you each
  path once it ends.
- **A sink** receives those closed paths. `LocalNightSink` writes them to
  IndexedDB. Implement the same handful of methods to upload them instead.

The library never touches your markup, your router or your framework. It decides;
your page plumbs.

## Watching a room

```js
import {
    calibrate,
    calibrationOutcome,
    Camera,
    DEFAULT_PARAMS,
    Detector,
    LEAVE_ROOM_SECONDS,
    LocalNightSink,
    openNightStore,
    PREFLIGHT_MESSAGE,
    Tracker,
    WakeLock,
} from '@mavware/bug-surveillance';

const camera = new Camera(document.querySelector('video'), DEFAULT_PARAMS.procWidth);
const wakeLock = new WakeLock(() => console.warn('This screen will not stay on by itself.'));

// The order matters. Ask before the camera opens, and let the room empty before
// anything is measured: whoever pressed start would otherwise be baked into the
// background model and recorded walking out.
if (!window.confirm(PREFLIGHT_MESSAGE)) {
    return;
}

await camera.start();
await new Promise((resolve) => setTimeout(resolve, LEAVE_ROOM_SECONDS * 1000));

const calibration = await calibrate(camera);

if (calibrationOutcome(calibration).blocked) {
    throw new Error('Too dark to see anything.');
}

const settings = { ...DEFAULT_PARAMS, diffThreshold: calibration.diffThreshold };
const sink = new LocalNightSink({
    store: await openNightStore(),
    reportUrlTemplate: 'report.html?night=<LOCAL_ID_PLACEHOLDER>',
});

await sink.storeReference({
    blob: await camera.captureReferenceJpeg(),
    frameWidth: camera.frameWidth,
    frameHeight: camera.frameHeight,
    settings,
});

const startedAt = Date.now();
const detector = new Detector(settings);
const tracker = new Tracker({
    scale: camera.scale,
    sessionStartTime: startedAt,
    captureCrop: (x, y) => camera.captureCropBase64(x, y),
    onTrackClosed: (track) => sink.enqueue(track),
});

sink.start();
await wakeLock.acquire();

const loop = setInterval(() => {
    tracker.update(detector.detect(camera.grabProcessedFrame()));
}, 1000 / settings.processFps);
```

Ending the night flushes what is queued and hands back where its report lives:

```js
clearInterval(loop);
tracker.flush();
sink.stop();
await sink.flush();
camera.stop();
await wakeLock.release();

const { reportUrl } = await sink.end({
    endedAtOffsetMs: Date.now() - startedAt,
    aborted: false,
});

window.location.assign(reportUrl);
```

## Reading a night back

`Replay` draws the trails and the animated playback over the reference photo,
and `mountReportControls` wires the play, speed, scrub and trail controls to it.

```js
import {
    buildLocalReportPayload,
    mountReportControls,
    openNightStore,
    sightingRows,
    statTiles,
} from '@mavware/bug-surveillance';

const store = await openNightStore();
const night = await store.getNight(nightId);
const tracks = await store.listTracks(nightId);

const { rebuild } = mountReportControls(document.getElementById('report'), {
    loadData: async () => ({ data: buildLocalReportPayload(night, tracks), referenceImage }),
});
```

`mountReportControls` is the one part with a markup contract. Inside the element
you hand it, it looks for `data-report="canvas"`, `"play"`, `"speed"`, `"scrub"`,
`"clock"` and `"trails"`, and treats a click on any element carrying
`data-track-id` as a request to highlight that trail. Use `Replay` directly if
you would rather own the controls.

`sightingRows` and `statTiles` give you the table rows and headline figures
ready to render, and `computeNightAnalytics` produces the summary behind them:
how many sightings, and which edges of the frame they clustered against.

## Sending nights somewhere else

Anything with these methods can stand in for `LocalNightSink`, so the capture
code above is unchanged whether nights stay on the device or go to a server:

```js
class UploadingSink {
    async storeReference({ blob, frameWidth, frameHeight, settings }) {}
    start() {}
    stop() {}
    enqueue(closedTrack) {}
    async flush({ keepalive } = {}) {}
    async end({ endedAtOffsetMs, aborted }) {
        return { ok: true, status: 200, reportUrl: '/report' };
    }
}
```

A closed track arrives with a client-generated id, its start and end offsets in
milliseconds, its points as `[offsetMs, x, y]` in full-frame pixels, and two
optional snapshots as raw base64 JPEG.

## Wording

Copy ships as exported constants, and the helpers that produce copy take an
optional overrides object, so you can reword or translate without forking:

```js
watchingState(true, { largeMotion: 'Quelqu\'un est dans la pièce.' });
calibrationOutcome(calibration, { tooDark: 'Trop sombre.' });
```

## Browser support

Needs `getUserMedia`, canvas and IndexedDB, so any current version of Chrome,
Edge, Firefox or Safari. The camera requires a secure context, meaning HTTPS or
localhost. The screen wake lock is used when available and degrades to a warning
through the `WakeLock` callback when it is not. When IndexedDB is unavailable,
such as in some private browsing modes, `openNightStore` falls back to an
in-memory store and marks it `volatile` so you can tell the user their night
will not survive the tab.

## Tuning detection

`DEFAULT_PARAMS` is sized for cockroaches on a kitchen floor at about a
two-metre camera distance. The knobs worth reaching for first:

- `minArea` and `maxArea` bound a single bug in processing pixels
- `maxChangedArea` is the whole-frame budget above which a frame is treated as a
  person or a pet and dropped
- `diffThreshold` is normally supplied by `calibrate`, which measures this
  camera's noise floor in this light
- `darkerThanBackground` assumes dark bugs on a lighter floor; turn it off to
  track anything that moves

## License

MIT
