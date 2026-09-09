// A compile-only exercise of the whole public API. It is never executed: the
// filename does not match the test runner's pattern, and `npm run typecheck` is
// what reads it. Its job is to catch a declaration that is inconsistent,
// unusable, or missing once someone actually imports the package.
import {
    BRIGHTNESS_BLOCK,
    BRIGHTNESS_WARN,
    Camera,
    CAMERA_CHECK_MESSAGE,
    calibrate,
    calibrationOutcome,
    cameraCheckLabel,
    classifyEdge,
    clusterEdgePoints,
    computeNightAnalytics,
    countdownMessage,
    DEFAULT_PARAMS,
    Detector,
    DIM_MESSAGE,
    EDGE_BINS,
    finalizeInterruptedNight,
    formatClock,
    HEARTBEAT_INTERVAL_MS,
    InMemoryNightStore,
    LARGE_MOTION_MESSAGE,
    LEAVE_ROOM_SECONDS,
    LOCAL_ID_PLACEHOLDER,
    LOCAL_STORAGE_NOTICE,
    LocalNightSink,
    loadImage,
    localTrackFromClosed,
    mergeAdjacentBins,
    MISSING_NIGHT_MESSAGE,
    mountReportControls,
    NIGHT_BOUNDARY_HOUR,
    nightAnalytics,
    nightDateFor,
    nightName,
    nightRows,
    openNightStore,
    overlayBoxes,
    PREFLIGHT_MESSAGE,
    referenceBlobKey,
    Replay,
    reportHeader,
    reportUrlFor,
    sightingRows,
    statTiles,
    STORE_NAME,
    STORE_VERSION,
    statTiles as statTilesAlias,
    TOO_DARK_MESSAGE,
    toGrayscale,
    TRACKER_DEFAULTS,
    Tracker,
    trackEdges,
    trackIdFrom,
    upgradeSchema,
    VOLATILE_STORE_MESSAGE,
    WATCHING_MESSAGE,
    WakeLock,
    wakeLockMessage,
    watchingState,
    buildLocalNight,
    buildLocalReportPayload,
} from '@mavware/bug-surveillance';
import type {
    ClosedTrack,
    DetectedBlob,
    NightAnalytics,
    NightSink,
    NightStore,
    ReportPayload,
    StoredNight,
    StoredTrack,
    TrackPoint,
    Zone,
} from '@mavware/bug-surveillance';

declare function expectType<T>(value: T): void;

// --- Seeing -----------------------------------------------------------------

const video = document.createElement('video');
const camera = new Camera(video, DEFAULT_PARAMS.procWidth);

expectType<Promise<void>>(camera.start());
expectType<number>(camera.scale);
expectType<string>(camera.captureCropBase64(10, 20));
expectType<string>(camera.captureCropBase64(10, 20, 64));
expectType<Promise<Blob | null>>(camera.captureReferenceJpeg());
camera.stop();

expectType<number>(BRIGHTNESS_BLOCK);
expectType<number>(BRIGHTNESS_WARN);
expectType<Float32Array>(toGrayscale(camera.grabProcessedFrame()));
expectType<Float32Array>(toGrayscale({ width: 2, height: 2, data: new Uint8ClampedArray(16) }));

async function calibrating() {
    const calibration = await calibrate(camera);
    expectType<boolean>(calibration.tooDark);
    expectType<number>(calibration.diffThreshold);

    // The messages object is optional, and partial when given.
    expectType<string | null>(calibrationOutcome(calibration).banner);
    expectType<boolean>(calibrationOutcome(calibration, { tooDark: 'Lights, please.' }).blocked);
}

const detector = new Detector({ maxChangedArea: 900, darkerThanBackground: false });
const blobs: DetectedBlob[] = detector.detect(camera.grabProcessedFrame());
expectType<number>(blobs[0].box.width);
expectType<boolean>(detector.largeMotion);
detector.reset();

const tracker = new Tracker({
    scale: camera.scale,
    sessionStartTime: Date.now(),
    captureCrop: (x: number, y: number) => camera.captureCropBase64(x, y),
    onTrackClosed: (track: ClosedTrack) => {
        expectType<string>(track.client_track_id);
        expectType<TrackPoint[]>(track.points);
        expectType<string | null>(track.start_crop);
    },
    params: { minPoints: 4 },
});

tracker.update(blobs);
tracker.update(blobs, Date.now());
expectType<number>(tracker.active.length);
expectType<number>(tracker.closedCount);
expectType<number>(TRACKER_DEFAULTS.maxMatchDistance);
tracker.flush();

const wakeLock = new WakeLock(() => {});
expectType<Promise<void>>(wakeLock.acquire());
expectType<Promise<void>>(wakeLock.release());
new WakeLock();

// --- Deciding ---------------------------------------------------------------

expectType<string>(PREFLIGHT_MESSAGE);
expectType<string>(TOO_DARK_MESSAGE);
expectType<string>(DIM_MESSAGE);
expectType<string>(CAMERA_CHECK_MESSAGE);
expectType<string>(WATCHING_MESSAGE);
expectType<string>(LARGE_MOTION_MESSAGE);
expectType<number>(LEAVE_ROOM_SECONDS);
expectType<string>(countdownMessage(3));
expectType<string>(cameraCheckLabel(true));
expectType<string>(cameraCheckLabel(false, { check: 'Preview' }));
expectType<string>(wakeLockMessage());
expectType<string>(wakeLockMessage(navigator.userAgent));
expectType<string>(watchingState(true));
expectType<string>(watchingState(false, { watching: 'On watch' }));
expectType<string>(formatClock(65000));

const boxes = overlayBoxes(blobs, {
    canvasWidth: 640,
    canvasHeight: 360,
    procWidth: 320,
    procHeight: 180,
});
expectType<number>(boxes[0].x);

// --- Summarising ------------------------------------------------------------

expectType<number>(EDGE_BINS);
expectType<'left' | 'right' | 'top' | 'bottom' | 'interior'>(classifyEdge([5, 360], 1280, 720));

const zones: Zone[] = clusterEdgePoints([[5, 360], [1270, 300]], 1280, 720);
expectType<'left' | 'right' | 'top' | 'bottom'>(zones[0].edge);
expectType<[number, number]>(zones[0].center);

expectType<Array<[number, number, number]>>(mergeAdjacentBins([1, 0, 2]));

const points: TrackPoint[] = [[0, 5, 360], [1000, 640, 715]];
const edges = trackEdges(points, 1280, 720);
expectType<'left' | 'right' | 'top' | 'bottom' | 'interior' | null>(edges.entryEdge);

const analytics: NightAnalytics = computeNightAnalytics({
    tracks: [{ points, pointCount: 2, dismissedAt: null }],
    startedAt: 1,
    endedAt: 2,
    frameWidth: 1280,
    frameHeight: 720,
});
expectType<number>(analytics.track_count);
expectType<Zone[]>(analytics.entry_zones);
// Every field but the tracks is optional.
computeNightAnalytics({ tracks: [{ points }] });

// --- Keeping ----------------------------------------------------------------

expectType<string>(STORE_NAME);
expectType<number>(STORE_VERSION);
declare const database: IDBDatabase;
upgradeSchema(database);

async function storing() {
    const store: NightStore = await openNightStore();
    const memory: NightStore = new InMemoryNightStore();
    await openNightStore({ indexedDB: null });

    expectType<boolean>(store.volatile);

    const night: StoredNight = buildLocalNight({
        id: crypto.randomUUID(),
        startedAt: Date.now(),
        frameWidth: 1280,
        frameHeight: 720,
        settings: { ...DEFAULT_PARAMS },
    });

    await store.putNight(night);
    expectType<StoredNight | null>(await store.getNight(night.id));
    expectType<StoredNight | null>(await store.patchNight(night.id, { status: 'completed' }));
    expectType<StoredNight[]>(await store.listNights());

    const track: StoredTrack = localTrackFromClosed(
        {
            client_track_id: 't1',
            start_offset_ms: 0,
            end_offset_ms: 1,
            points,
            start_crop: null,
            end_crop: null,
        },
        night.id,
        1280,
        720,
    );

    await memory.putTrack(track);
    const tracks: StoredTrack[] = await memory.listTracks(night.id);
    await memory.patchTrack(night.id, track.clientTrackId, { dismissedAt: Date.now() });
    await memory.putBlob({
        key: referenceBlobKey(night.id),
        nightId: night.id,
        bytes: new ArrayBuffer(8),
        type: 'image/jpeg',
    });
    expectType<ArrayBuffer | undefined>((await memory.getBlob(referenceBlobKey(night.id)))?.bytes);
    await memory.deleteNight(night.id);

    // A sink is interchangeable: the local one satisfies the interface.
    const sink: NightSink = new LocalNightSink({
        store,
        reportUrlTemplate: reportUrlFor('report.html?night=' + LOCAL_ID_PLACEHOLDER, LOCAL_ID_PLACEHOLDER),
        onStatus: (status) => expectType<number | undefined>(status.queueDepth),
    });

    await sink.storeReference({
        blob: new Blob([]),
        frameWidth: 1280,
        frameHeight: 720,
        settings: { ...DEFAULT_PARAMS },
    });
    sink.start();
    sink.enqueue({
        client_track_id: 't2',
        start_offset_ms: 0,
        end_offset_ms: 1,
        points,
        start_crop: null,
        end_crop: null,
    });
    await sink.flush();
    await sink.flush({ keepalive: true });
    expectType<string | null>((await sink.end({ endedAtOffsetMs: 1000, aborted: false })).reportUrl);
    sink.stop();

    expectType<number>(HEARTBEAT_INTERVAL_MS);
    expectType<number>(NIGHT_BOUNDARY_HOUR);
    expectType<string>(LOCAL_STORAGE_NOTICE);
    expectType<string>(VOLATILE_STORE_MESSAGE);
    expectType<string>(MISSING_NIGHT_MESSAGE);
    expectType<Date>(nightDateFor(Date.now()));
    expectType<string>(nightName(Date.now()));
    expectType<NightAnalytics>(nightAnalytics(night, tracks));

    // --- Showing ------------------------------------------------------------

    const payload: ReportPayload = buildLocalReportPayload(night, tracks);
    expectType<string>(payload.tracks[0].id);

    expectType<string>(sightingRows(night, tracks)[0].time);
    expectType<string | null>(sightingRows(night, tracks)[0].startCropSrc);
    expectType<number>(statTiles(night.analytics).trackCount);
    expectType<string>(statTilesAlias(null).topEntry);
    expectType<boolean>(reportHeader(night).discarded);
    expectType<string>(nightRows([night], { reportUrlTemplate: 'report.html' })[0].reportUrl);
    expectType<number | null | undefined>(finalizeInterruptedNight(night, tracks)?.endedAt);

    const canvas = document.createElement('canvas');
    const image = await loadImage('reference.jpg');
    const replay = new Replay({ canvas, data: payload, referenceImage: image });

    expectType<number | null>(replay.playheadMs);
    replay.speed = 60;
    replay.showTrails = false;
    replay.highlightedTrackId = trackIdFrom('t1');
    replay.onFrame = (fraction: number) => expectType<number>(fraction);
    replay.play();
    replay.seek(0.5);
    replay.pause();
    replay.stopReplay();
    replay.draw();
    expectType<string>(replay.trackColor(0, 0.5));

    new Replay({ canvas, data: payload });

    const controls = mountReportControls(document.body, {
        loadData: async () => ({ data: payload, referenceImage: image }),
    });
    await controls.ready;
    await controls.rebuild();
}

export { calibrating, storing };
