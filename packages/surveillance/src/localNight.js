// The decisions behind a night that never reaches the server: what a stored
// night looks like, how it is named, how its tracks and report are shaped, and
// what the pages say about it. Pure, so it is tested in node; the pages and the
// sink are plumbing that call it. Names and shapes mirror the server's so a
// night claimed into an account later is indistinguishable from a live one.
import { computeNightAnalytics, trackEdges } from './sessionAnalytics.js';

/**
 * A night begun after midnight still belongs to the evening before, the same
 * rule SurveillanceSession::nightDateFor applies on the server.
 */
export const NIGHT_BOUNDARY_HOUR = 6;

/**
 * The uuid the watch report route is generated with, for the JS to swap out.
 * Routes are named server-side and the page cannot know a night's id up front.
 */
export const LOCAL_ID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

export const LOCAL_STORAGE_NOTICE =
    'Nights recorded here stay in this browser on this device. Clearing site data removes them. Create an account to keep them and see trends across nights.';

export const VOLATILE_STORE_MESSAGE =
    'This browser will not keep nights between visits — the report will be gone once this tab closes. Try a normal (non-private) window to keep them.';

export const MISSING_NIGHT_MESSAGE =
    'This night is not stored in this browser. Nights are kept only on the device that recorded them.';

export function nightDateFor(ms) {
    const date = new Date(ms - NIGHT_BOUNDARY_HOUR * 60 * 60 * 1000);
    date.setHours(0, 0, 0, 0);

    return date;
}

/** "Night of Sep 8", the shape the dashboard gives a session when it is created. */
export function nightName(startedAt) {
    return `Night of ${nightDateFor(startedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
}

export function buildLocalNight({ id, startedAt, frameWidth, frameHeight, settings }) {
    return {
        id,
        name: nightName(startedAt),
        status: 'active',
        startedAt,
        endedAt: null,
        lastHeartbeatAt: startedAt,
        frameWidth,
        frameHeight,
        settings,
        analytics: null,
        createdAt: startedAt,
        claimedSessionId: null,
        claimedAt: null,
    };
}

export function referenceBlobKey(nightId) {
    return `${nightId}/reference`;
}

export function reportUrlFor(template, nightId) {
    return template.replace(LOCAL_ID_PLACEHOLDER, nightId);
}

/**
 * A closed track from the tracker, as the store keeps it: the server's insert
 * shape with edges classified up front, as CaptureController does.
 */
export function localTrackFromClosed(track, nightId, frameWidth, frameHeight) {
    return {
        nightId,
        clientTrackId: track.client_track_id,
        startOffsetMs: track.start_offset_ms,
        endOffsetMs: track.end_offset_ms,
        pointCount: track.points.length,
        points: track.points,
        ...trackEdges(track.points, frameWidth, frameHeight),
        startCrop: track.start_crop ?? null,
        endCrop: track.end_crop ?? null,
        dismissedAt: null,
    };
}

export function nightAnalytics(night, tracks) {
    return computeNightAnalytics({
        tracks,
        startedAt: night.startedAt,
        endedAt: night.endedAt,
        frameWidth: night.frameWidth,
        frameHeight: night.frameHeight,
    });
}

/** The payload replay.js draws from, in exactly the shape the server's report page emits. */
export function buildLocalReportPayload(night, tracks) {
    return {
        frameWidth: night.frameWidth,
        frameHeight: night.frameHeight,
        analytics: night.analytics ?? nightAnalytics(night, tracks),
        tracks: tracks
            .filter((track) => track.dismissedAt == null)
            .sort((a, b) => a.startOffsetMs - b.startOffsetMs)
            .map((track) => ({
                id: track.clientTrackId,
                startOffsetMs: track.startOffsetMs,
                endOffsetMs: track.endOffsetMs,
                points: track.points,
                entryEdge: track.entryEdge,
                exitEdge: track.exitEdge,
            })),
    };
}

function capitalise(value) {
    return value === null || value === undefined ? '—' : value.charAt(0).toUpperCase() + value.slice(1);
}

function clockAt(ms) {
    return new Date(ms).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

/** One row per track for the sightings table, dismissed rows included but marked. */
export function sightingRows(night, tracks) {
    return [...tracks]
        .sort((a, b) => a.startOffsetMs - b.startOffsetMs)
        .map((track) => ({
            clientTrackId: track.clientTrackId,
            time: clockAt(night.startedAt + track.startOffsetMs),
            durationSeconds: Math.round((track.endOffsetMs - track.startOffsetMs) / 100) / 10,
            entered: capitalise(track.entryEdge),
            exited: capitalise(track.exitEdge),
            startCropSrc: track.startCrop ? `data:image/jpeg;base64,${track.startCrop}` : null,
            endCropSrc: track.endCrop ? `data:image/jpeg;base64,${track.endCrop}` : null,
            dismissed: track.dismissedAt != null,
        }));
}

function topZoneLabel(zones) {
    return zones && zones.length > 0 ? `${capitalise(zones[0].edge)} edge` : 'None';
}

export function statTiles(analytics) {
    return {
        trackCount: analytics?.track_count ?? 0,
        topEntry: topZoneLabel(analytics?.entry_zones),
        topExit: topZoneLabel(analytics?.exit_zones),
    };
}

function dateRange(night) {
    const format = (ms) => new Date(ms).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });

    return night.endedAt === null ? format(night.startedAt) : `${format(night.startedAt)} – ${format(night.endedAt)}`;
}

/** What the report header says about a night. */
export function reportHeader(night) {
    return {
        title: night.name,
        range: dateRange(night),
        discarded: night.status === 'aborted',
    };
}

const STATUS_LABELS = { active: 'Interrupted', completed: 'Completed', aborted: 'Discarded' };

/** One row per stored night for the list on the watch page. */
export function nightRows(nights, { reportUrlTemplate }) {
    return nights.map((night) => ({
        id: night.id,
        name: night.name,
        started: dateRange({ ...night, endedAt: null }),
        status: STATUS_LABELS[night.status] ?? night.status,
        sightings: night.analytics?.track_count ?? 0,
        reportUrl: reportUrlFor(reportUrlTemplate, night.id),
        claimed: night.claimedSessionId !== null,
    }));
}

/**
 * A night still marked active when a page loads is one whose tab died
 * overnight. Close it at the last heartbeat, as the server's stale-device
 * warning would have the user do, so its report can be read.
 */
export function finalizeInterruptedNight(night, tracks) {
    if (night.status !== 'active') {
        return null;
    }

    const endedAt = Math.max(night.startedAt, night.lastHeartbeatAt ?? night.startedAt);
    const closed = { ...night, status: 'completed', endedAt };

    return { status: 'completed', endedAt, analytics: nightAnalytics(closed, tracks) };
}
