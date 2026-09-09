// Type declarations for @mavware/bug-surveillance.
//
// Hand-written rather than generated, so the shapes that matter, such as a
// stored night, a closed track and the report payload, are named and precise
// instead of widened to `any`. `npm run typecheck` in this package compiles
// these declarations together with a type test that exercises the whole public
// API, which catches an inconsistent or unusable signature but cannot prove the
// declarations match the runtime: if you change a signature in src, change it
// here in the same commit.
//
// This is a browser library: it references DOM types, so a consumer's tsconfig
// needs "lib": ["ES2022", "DOM"].

// ---------------------------------------------------------------------------
// Shared shapes
// ---------------------------------------------------------------------------

/** A frame's pixels. Real `ImageData` satisfies this, and so does a plain object in tests. */
export interface ImageLike {
    width: number;
    height: number;
    data: Uint8ClampedArray;
}

/** One sample on a track: milliseconds since the night began, then x and y in full-frame pixels. */
export type TrackPoint = [offsetMs: number, x: number, y: number];

/** Which side of the frame a point sits against, or the middle of it. */
export type Edge = 'left' | 'right' | 'top' | 'bottom';
export type EdgeOrInterior = Edge | 'interior';

/** Where a night stands. A night is `active` until it is ended or discarded. */
export type NightStatus = 'active' | 'completed' | 'aborted';

// ---------------------------------------------------------------------------
// Camera
// ---------------------------------------------------------------------------

/**
 * Owns the camera stream and two canvases: a full-resolution one for reference
 * frames and crops, and a downscaled one for per-frame processing.
 */
export declare class Camera {
    constructor(videoElement: HTMLVideoElement, processingWidth?: number);

    video: HTMLVideoElement;
    processingWidth: number;
    stream: MediaStream | null;
    fullCanvas: HTMLCanvasElement;
    procCanvas: HTMLCanvasElement;

    /** Set once `start()` has resolved. */
    frameWidth: number;
    frameHeight: number;
    /** Full-frame pixels per processing pixel. */
    scale: number;

    /** Opens the stream. Rejects if the user refuses the camera prompt. */
    start(): Promise<void>;
    stop(): void;

    grabProcessedFrame(): ImageData;
    captureReferenceJpeg(): Promise<Blob | null>;

    /** A small crop as raw base64, with no `data:` prefix, so it can ride inside JSON. */
    captureCropBase64(centerX: number, centerY: number, size?: number): string;
}

// ---------------------------------------------------------------------------
// Brightness and calibration
// ---------------------------------------------------------------------------

/** Mean luminance at or below which a scene is refused outright. */
export declare const BRIGHTNESS_BLOCK: number;
/** Mean luminance below which a scene is watched, but warned about. */
export declare const BRIGHTNESS_WARN: number;

export interface Calibration {
    meanLuminance: number;
    tooDark: boolean;
    dim: boolean;
    /** The motion threshold this camera needs in this light. */
    diffThreshold: number;
}

/** Watches an empty room for a few seconds to measure its light and its noise floor. */
export declare function calibrate(
    camera: Pick<Camera, 'grabProcessedFrame'>,
    durationMs?: number,
    sampleIntervalMs?: number,
): Promise<Calibration>;

export declare function toGrayscale(imageData: ImageLike): Float32Array;

// ---------------------------------------------------------------------------
// Detection
// ---------------------------------------------------------------------------

export interface DetectorParams {
    processFps: number;
    procWidth: number;
    /** How fast the background model absorbs what it sees. */
    bgAlpha: number;
    diffThreshold: number;
    minArea: number;
    maxArea: number;
    /** Moving pixels a whole frame may hold before it is dropped as a person or a pet. */
    maxChangedArea: number;
    maxAspectRatio: number;
    darkerThanBackground: boolean;
    darkMargin: number;
}

export declare const DEFAULT_PARAMS: DetectorParams;

export interface DetectedBlob {
    cx: number;
    cy: number;
    area: number;
    box: { x: number; y: number; width: number; height: number };
}

/**
 * Frame-differencing blob detector: keeps a running-average background,
 * thresholds the difference, and extracts roach-sized connected components.
 */
export declare class Detector {
    constructor(params?: Partial<DetectorParams>);

    params: DetectorParams;
    background: Float32Array | null;

    /**
     * True while the last frame was dropped for holding something far larger
     * than a bug. Read it to tell the user why nothing is being reported.
     */
    largeMotion: boolean;

    /** Blobs in processing-canvas coordinates. The first frame only seeds the background. */
    detect(imageData: ImageLike): DetectedBlob[];
    reset(): void;
}

// ---------------------------------------------------------------------------
// Tracking
// ---------------------------------------------------------------------------

export interface TrackerParams {
    /** Processing pixels a blob may move between frames and still be the same bug. */
    maxMatchDistance: number;
    confirmAfterHits: number;
    closeAfterMisses: number;
    minPoints: number;
    /** Discards jitter that never went anywhere. */
    minDisplacement: number;
    maxPointsPerTrack: number;
    maxTrackDurationMs: number;
}

export declare const TRACKER_DEFAULTS: TrackerParams;

/** A track that has ended, handed to the sink. Field names match the wire format. */
export interface ClosedTrack {
    client_track_id: string;
    start_offset_ms: number;
    end_offset_ms: number;
    points: TrackPoint[];
    start_crop: string | null;
    end_crop: string | null;
}

/** A track still being followed. */
export interface ActiveTrack {
    id: string;
    points: TrackPoint[];
    lastX: number;
    lastY: number;
    hits: number;
    misses: number;
    startCrop: string | null;
    endCrop: string | null;
}

/**
 * Associates per-frame blobs into tracks by nearest neighbour, and hands closed
 * tracks, scaled to full-frame pixels, to `onTrackClosed`.
 */
export declare class Tracker {
    constructor(options: {
        /** Full-frame pixels per processing pixel, from `Camera.scale`. */
        scale: number;
        sessionStartTime: number;
        captureCrop: (x: number, y: number) => string | null;
        onTrackClosed: (track: ClosedTrack) => void;
        params?: Partial<TrackerParams>;
    });

    params: TrackerParams;
    candidates: ActiveTrack[];
    active: ActiveTrack[];
    closedCount: number;

    update(blobs: DetectedBlob[], now?: number): void;

    /** Closes everything still open, for the end of a night. */
    flush(): void;
}

// ---------------------------------------------------------------------------
// Wake lock
// ---------------------------------------------------------------------------

/**
 * Holds the screen awake for the night, re-acquiring the lock whenever the page
 * becomes visible again. `onUnsupported` fires when the browser refuses.
 */
export declare class WakeLock {
    constructor(onUnsupported?: () => void);

    acquire(): Promise<void>;
    release(): Promise<void>;
}

// ---------------------------------------------------------------------------
// What a capture page says and shows
// ---------------------------------------------------------------------------

export declare const TOO_DARK_MESSAGE: string;
export declare const DIM_MESSAGE: string;
export declare const PREFLIGHT_MESSAGE: string;
export declare const CAMERA_CHECK_MESSAGE: string;
export declare const WATCHING_MESSAGE: string;
export declare const LARGE_MOTION_MESSAGE: string;

/** Seconds the room is left alone before anything is measured. */
export declare const LEAVE_ROOM_SECONDS: number;

export declare function countdownMessage(secondsLeft: number): string;

export declare function cameraCheckLabel(
    previewing: boolean,
    messages?: { stop?: string; check?: string },
): string;

/** Names the actual OS setting to change, chosen from the user agent. */
export declare function wakeLockMessage(userAgent?: string): string;

/** Whether a calibrated scene can be watched, and what to say about it. */
export declare function calibrationOutcome(
    calibration: Pick<Calibration, 'tooDark' | 'dim'>,
    messages?: { tooDark?: string; dim?: string },
): { blocked: boolean; banner: string | null };

/** The status line while a night runs, given the detector's `largeMotion`. */
export declare function watchingState(
    largeMotion: boolean,
    messages?: { watching?: string; largeMotion?: string },
): string;

export interface OverlayBox {
    x: number;
    y: number;
    width: number;
    height: number;
}

/** Detection boxes scaled from the processing canvas up to an on-screen overlay. */
export declare function overlayBoxes(
    blobs: DetectedBlob[],
    canvas: { canvasWidth: number; canvasHeight: number; procWidth: number; procHeight: number },
): OverlayBox[];

/** Milliseconds as HH:MM:SS, for a clock that may run all night. */
export declare function formatClock(ms: number): string;

// ---------------------------------------------------------------------------
// Analytics
// ---------------------------------------------------------------------------

export declare const EDGE_BINS: number;

/** A stretch of one frame edge that several bugs crossed. */
export interface Zone {
    edge: Edge;
    from: number;
    to: number;
    center: [x: number, y: number];
    count: number;
}

export declare function classifyEdge(
    point: [x: number, y: number],
    width: number,
    height: number,
): EdgeOrInterior;

export declare function clusterEdgePoints(
    points: Array<[x: number, y: number]>,
    width: number,
    height: number,
): Zone[];

/** Runs of adjacent non-empty bins, as `[fromBin, toBin, count]`. */
export declare function mergeAdjacentBins(bins: number[]): Array<[number, number, number]>;

export declare function trackEdges(
    points: TrackPoint[],
    width: number,
    height: number,
): { entryEdge: EdgeOrInterior | null; exitEdge: EdgeOrInterior | null };

/** The summary stored on a night. Keys are snake_case to match the server's. */
export interface NightAnalytics {
    track_count: number;
    total_points: number;
    duration_ms: number;
    entry_zones: Zone[];
    exit_zones: Zone[];
}

/** Anything with points and a dismissal can be summarised. Dismissed tracks never count. */
export interface AnalysableTrack {
    points: TrackPoint[];
    pointCount?: number;
    dismissedAt?: number | null;
}

export declare function computeNightAnalytics(input: {
    tracks: AnalysableTrack[];
    startedAt?: number | null;
    endedAt?: number | null;
    frameWidth?: number;
    frameHeight?: number;
}): NightAnalytics;

// ---------------------------------------------------------------------------
// Keeping a night in the browser
// ---------------------------------------------------------------------------

export interface StoredNight {
    id: string;
    name: string;
    status: NightStatus;
    startedAt: number;
    endedAt: number | null;
    lastHeartbeatAt: number;
    frameWidth: number;
    frameHeight: number;
    settings: Record<string, unknown>;
    analytics: NightAnalytics | null;
    createdAt: number;
    /** Set once the night has been copied somewhere durable, such as an account. */
    claimedSessionId: number | string | null;
    claimedAt: number | null;
}

export interface StoredTrack {
    nightId: string;
    clientTrackId: string;
    startOffsetMs: number;
    endOffsetMs: number;
    pointCount: number;
    points: TrackPoint[];
    entryEdge: EdgeOrInterior | null;
    exitEdge: EdgeOrInterior | null;
    /** Raw base64 JPEG, no `data:` prefix. */
    startCrop: string | null;
    endCrop: string | null;
    dismissedAt: number | null;
}

export interface StoredBlob {
    key: string;
    nightId: string;
    bytes: ArrayBuffer;
    type: string;
}

/** What both stores answer. Every method resolves; unknown keys give null. */
export interface NightStore {
    /** True when nothing written here survives the tab, so the page can warn. */
    volatile: boolean;

    putNight(night: StoredNight): Promise<void>;
    getNight(id: string): Promise<StoredNight | null>;
    patchNight(id: string, patch: Partial<StoredNight>): Promise<StoredNight | null>;
    /** Newest first. */
    listNights(): Promise<StoredNight[]>;
    /** Removes the night with its tracks and its blobs. */
    deleteNight(id: string): Promise<void>;

    putTrack(track: StoredTrack): Promise<void>;
    listTracks(nightId: string): Promise<StoredTrack[]>;
    patchTrack(
        nightId: string,
        clientTrackId: string,
        patch: Partial<StoredTrack>,
    ): Promise<StoredTrack | null>;

    putBlob(blob: StoredBlob): Promise<void>;
    getBlob(key: string): Promise<StoredBlob | null>;
}

export declare const STORE_NAME: string;
export declare const STORE_VERSION: number;

/** Builds the schema. Exported so a spec can run it against a fake factory. */
export declare function upgradeSchema(db: IDBDatabase): void;

/**
 * Opens the IndexedDB store, or falls back to an in-memory one when the browser
 * has no IndexedDB or refuses to open it. The fallback is marked volatile.
 */
export declare function openNightStore(options?: {
    indexedDB?: IDBFactory | null;
}): Promise<NightStore>;

export declare class IndexedDbNightStore implements NightStore {
    constructor(db: IDBDatabase);
    volatile: boolean;
    putNight(night: StoredNight): Promise<void>;
    getNight(id: string): Promise<StoredNight | null>;
    patchNight(id: string, patch: Partial<StoredNight>): Promise<StoredNight | null>;
    listNights(): Promise<StoredNight[]>;
    deleteNight(id: string): Promise<void>;
    putTrack(track: StoredTrack): Promise<void>;
    listTracks(nightId: string): Promise<StoredTrack[]>;
    patchTrack(nightId: string, clientTrackId: string, patch: Partial<StoredTrack>): Promise<StoredTrack | null>;
    putBlob(blob: StoredBlob): Promise<void>;
    getBlob(key: string): Promise<StoredBlob | null>;
}

/** The runtime fallback, and the double to test against. */
export declare class InMemoryNightStore implements NightStore {
    volatile: boolean;
    putNight(night: StoredNight): Promise<void>;
    getNight(id: string): Promise<StoredNight | null>;
    patchNight(id: string, patch: Partial<StoredNight>): Promise<StoredNight | null>;
    listNights(): Promise<StoredNight[]>;
    deleteNight(id: string): Promise<void>;
    putTrack(track: StoredTrack): Promise<void>;
    listTracks(nightId: string): Promise<StoredTrack[]>;
    patchTrack(nightId: string, clientTrackId: string, patch: Partial<StoredTrack>): Promise<StoredTrack | null>;
    putBlob(blob: StoredBlob): Promise<void>;
    getBlob(key: string): Promise<StoredBlob | null>;
}

// ---------------------------------------------------------------------------
// Sinks
// ---------------------------------------------------------------------------

export interface NightSinkStatus {
    queueDepth?: number;
    lastError?: string;
    /** Set by a sink that uploads, when the session behind it expired. */
    authLost?: boolean;
}

export interface NightSinkResult {
    ok: boolean;
    status: number;
    reportUrl: string | null;
}

/**
 * Where a night goes. Implement this to upload nights instead of keeping them
 * locally; a capture page calls nothing else, so the two are interchangeable.
 */
export interface NightSink {
    storeReference(input: {
        blob: Blob;
        frameWidth: number;
        frameHeight: number;
        settings: Record<string, unknown>;
    }): Promise<void>;

    start(): void;
    stop(): void;
    enqueue(track: ClosedTrack): void;
    flush(options?: { keepalive?: boolean }): Promise<void>;
    end(input: { endedAtOffsetMs: number; aborted: boolean }): Promise<NightSinkResult>;
}

/** How often the local sink records that the night is still running. */
export declare const HEARTBEAT_INTERVAL_MS: number;

/** The sink that keeps a night in this browser and never touches the network. */
export declare class LocalNightSink implements NightSink {
    constructor(options: {
        store: NightStore;
        /** A URL containing `LOCAL_ID_PLACEHOLDER`, swapped for the night's id. */
        reportUrlTemplate: string;
        onStatus?: (status: NightSinkStatus) => void;
    });

    /** The night being written, once `storeReference` has run. */
    nightId: string | null;

    storeReference(input: {
        blob: Blob;
        frameWidth: number;
        frameHeight: number;
        settings: Record<string, unknown>;
    }): Promise<void>;
    start(): void;
    stop(): void;
    enqueue(track: ClosedTrack): void;
    flush(options?: { keepalive?: boolean }): Promise<void>;
    end(input: { endedAtOffsetMs: number; aborted: boolean }): Promise<NightSinkResult>;
}

// ---------------------------------------------------------------------------
// A night's shape, naming and report
// ---------------------------------------------------------------------------

/** A night runs until this hour of the following morning. */
export declare const NIGHT_BOUNDARY_HOUR: number;

/** The uuid a report URL is generated with, for the page to swap out. */
export declare const LOCAL_ID_PLACEHOLDER: string;

export declare const LOCAL_STORAGE_NOTICE: string;
export declare const VOLATILE_STORE_MESSAGE: string;
export declare const MISSING_NIGHT_MESSAGE: string;

/** The evening a moment belongs to, even past midnight. */
export declare function nightDateFor(ms: number): Date;

/** For example, "Night of Sep 8". */
export declare function nightName(startedAt: number): string;

export declare function buildLocalNight(input: {
    id: string;
    startedAt: number;
    frameWidth: number;
    frameHeight: number;
    settings: Record<string, unknown>;
}): StoredNight;

export declare function referenceBlobKey(nightId: string): string;

export declare function reportUrlFor(template: string, nightId: string): string;

export declare function localTrackFromClosed(
    track: ClosedTrack,
    nightId: string,
    frameWidth: number,
    frameHeight: number,
): StoredTrack;

export declare function nightAnalytics(night: StoredNight, tracks: StoredTrack[]): NightAnalytics;

export interface ReportTrack {
    id: string;
    startOffsetMs: number;
    endOffsetMs: number;
    points: TrackPoint[];
    entryEdge: EdgeOrInterior | null;
    exitEdge: EdgeOrInterior | null;
}

/** What `Replay` draws. Dismissed tracks are already filtered out. */
export interface ReportPayload {
    frameWidth: number;
    frameHeight: number;
    analytics: NightAnalytics | null;
    tracks: ReportTrack[];
}

export declare function buildLocalReportPayload(
    night: StoredNight,
    tracks: StoredTrack[],
): ReportPayload;

/** One row of the sightings table. Dismissed tracks are included, and marked. */
export interface SightingRow {
    clientTrackId: string;
    time: string;
    durationSeconds: number;
    entered: string;
    exited: string;
    /** A `data:` URL ready for an `img`, or null when no crop was kept. */
    startCropSrc: string | null;
    endCropSrc: string | null;
    dismissed: boolean;
}

export declare function sightingRows(night: StoredNight, tracks: StoredTrack[]): SightingRow[];

export declare function statTiles(analytics: NightAnalytics | null | undefined): {
    trackCount: number;
    topEntry: string;
    topExit: string;
};

export declare function reportHeader(night: StoredNight): {
    title: string;
    range: string;
    discarded: boolean;
};

export interface NightRow {
    id: string;
    name: string;
    started: string;
    status: string;
    sightings: number;
    reportUrl: string;
    claimed: boolean;
}

export declare function nightRows(
    nights: StoredNight[],
    options: { reportUrlTemplate: string },
): NightRow[];

/**
 * A night still active when a page loads is one whose tab died overnight.
 * Returns the patch that closes it at its last heartbeat, or null if it is
 * already finished.
 */
export declare function finalizeInterruptedNight(
    night: StoredNight,
    tracks: StoredTrack[],
): { status: 'completed'; endedAt: number; analytics: NightAnalytics } | null;

// ---------------------------------------------------------------------------
// Replay
// ---------------------------------------------------------------------------

/**
 * Draws trails and the animated replay over the reference photo. Coordinates
 * are full-frame pixels; the canvas is sized to the frame and scaled by CSS.
 */
export declare class Replay {
    constructor(options: {
        canvas: HTMLCanvasElement;
        data: ReportPayload;
        referenceImage?: HTMLImageElement | null;
    });

    data: ReportPayload;
    duration: number;
    /** Null while the whole night is shown at once, rather than a moment in it. */
    playheadMs: number | null;
    playing: boolean;
    speed: number;
    showTrails: boolean;
    highlightedTrackId: string | number | null;
    /** Called on every animation frame with progress from zero to one. */
    onFrame?: (fraction: number) => void;

    draw(): void;
    play(): void;
    pause(): void;
    stopReplay(): void;
    seek(fraction: number): void;
    trackColor(index: number, alpha?: number): string;
}

/**
 * Wires the replay controls inside `root`. It reads these attributes:
 * `data-report="canvas"`, `"play"`, `"speed"`, `"scrub"`, `"clock"`, `"trails"`,
 * and treats a click on any `[data-track-id]` as a request to highlight it.
 */
export declare function mountReportControls(
    root: Element,
    options: {
        loadData: () => Promise<{
            data: ReportPayload;
            referenceImage: HTMLImageElement | null;
        }>;
    },
): {
    /** Rebuilds from a fresh payload, after a track is dismissed or restored. */
    rebuild: () => Promise<void>;
    /** Resolves once the first build has finished. */
    ready: Promise<void>;
};

/** Keeps a uuid a string and a numeric id a number, so `===` matches the payload. */
export declare function trackIdFrom(raw: string): string | number;

/** Resolves to null rather than rejecting when the image cannot be loaded. */
export declare function loadImage(url: string): Promise<HTMLImageElement | null>;
