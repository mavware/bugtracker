// @bugtracker/surveillance — overnight bug surveillance from a browser camera.
//
// Detection and tracking run entirely in the browser; a night can be kept in the
// browser's own IndexedDB store and reported on without any server at all. The
// consumer owns the page: it wires a camera, a detector and a tracker together,
// hands closed tracks to a "sink" (the LocalNightSink here, or one of its own
// that uploads them), and draws the report with Replay. Nothing in this package
// assumes a framework, a route, or a piece of markup.

// Seeing: the camera, calibration, and per-frame detection and tracking.
export { Camera } from './camera.js';
export { BRIGHTNESS_BLOCK, calibrate, toGrayscale } from './brightness.js';
export { DEFAULT_PARAMS, Detector } from './detector.js';
export { TRACKER_DEFAULTS, Tracker } from './tracker.js';
export { WakeLock } from './wakeLock.js';

// Deciding: what a capture page says and shows.
export {
    CAMERA_CHECK_MESSAGE,
    DIM_MESSAGE,
    LARGE_MOTION_MESSAGE,
    LEAVE_ROOM_SECONDS,
    PREFLIGHT_MESSAGE,
    TOO_DARK_MESSAGE,
    WATCHING_MESSAGE,
    calibrationOutcome,
    cameraCheckLabel,
    countdownMessage,
    formatClock,
    overlayBoxes,
    wakeLockMessage,
    watchingState,
} from './captureLogic.js';

// Summarising: the night's analytics.
export {
    EDGE_BINS,
    classifyEdge,
    clusterEdgePoints,
    computeNightAnalytics,
    mergeAdjacentBins,
    trackEdges,
} from './sessionAnalytics.js';

// Keeping: a night in the browser.
export { IndexedDbNightStore, STORE_NAME, STORE_VERSION, openNightStore, upgradeSchema } from './nightStore.js';
export { InMemoryNightStore } from './nightStoreMemory.js';
export { HEARTBEAT_INTERVAL_MS, LocalNightSink } from './localNightSink.js';
export {
    LOCAL_ID_PLACEHOLDER,
    LOCAL_STORAGE_NOTICE,
    MISSING_NIGHT_MESSAGE,
    NIGHT_BOUNDARY_HOUR,
    VOLATILE_STORE_MESSAGE,
    buildLocalNight,
    buildLocalReportPayload,
    finalizeInterruptedNight,
    localTrackFromClosed,
    nightAnalytics,
    nightDateFor,
    nightName,
    nightRows,
    referenceBlobKey,
    reportHeader,
    reportUrlFor,
    sightingRows,
    statTiles,
} from './localNight.js';

// Showing: the replay and its controls.
export { Replay } from './replay.js';
export { loadImage, mountReportControls, trackIdFrom } from './reportControls.js';
