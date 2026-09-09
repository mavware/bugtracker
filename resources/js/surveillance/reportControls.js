// The replay controls behind both report pages: play, speed, scrub, trails and
// row highlighting, over a Replay built from whatever loadData hands back. The
// logged-in report reads its payload from a JSON island the server re-renders;
// the local report reads it from the night store. Neither knows the other.
import { formatClock } from './captureLogic.js';
import { Replay } from './replay.js';

/**
 * @param {HTMLElement} root  The element holding the data-report controls.
 * @param {{ loadData: () => Promise<{ data: object, referenceImage: HTMLImageElement | null }> }} options
 * @returns {{ rebuild: () => Promise<void>, ready: Promise<void> }}
 */
export function mountReportControls(root, { loadData }) {
    const el = (name) => root.querySelector(`[data-report="${name}"]`);

    const canvas = el('canvas');
    const playButton = el('play');
    const speedSelect = el('speed');
    const scrub = el('scrub');
    const clock = el('clock');
    const trailsToggle = el('trails');

    let replay = null;

    const rebuild = async () => {
        replay?.pause();

        const { data, referenceImage } = await loadData();

        replay = new Replay({ canvas, data, referenceImage });
        replay.speed = Number(speedSelect.value ?? 60);
        replay.showTrails = trailsToggle.checked;
        replay.draw();

        replay.onFrame = (fraction) => {
            scrub.value = Math.round(fraction * 1000);
            clock.textContent = formatClock(replay.playheadMs);
            if (!replay.playing) {
                playButton.textContent = 'Replay';
            }
        };

        scrub.value = 0;
        clock.textContent = '–';
        playButton.textContent = 'Replay';
    };

    playButton.addEventListener('click', () => {
        if (replay.playing) {
            replay.pause();
            playButton.textContent = 'Replay';
        } else {
            replay.speed = Number(speedSelect.value ?? 60);
            replay.play();
            playButton.textContent = 'Pause';
        }
    });

    speedSelect.addEventListener('change', () => {
        replay.speed = Number(speedSelect.value);
    });

    scrub.addEventListener('input', () => {
        replay.pause();
        playButton.textContent = 'Replay';
        replay.seek(Number(scrub.value) / 1000);
        clock.textContent = formatClock(replay.playheadMs);
    });

    trailsToggle.addEventListener('change', () => {
        replay.showTrails = trailsToggle.checked;
        replay.draw();
    });

    // Delegated so listeners survive the table rows being re-rendered.
    root.addEventListener('click', (event) => {
        const row = event.target.closest('[data-track-id]');

        if (row === null || event.target.closest('button') !== null || replay === null) {
            return;
        }

        const trackId = trackIdFrom(row.dataset.trackId);
        replay.highlightedTrackId = replay.highlightedTrackId === trackId ? null : trackId;
        replay.draw();
    });

    return { rebuild, ready: rebuild() };
}

/**
 * Server tracks have numeric ids and local ones have uuids; Replay compares
 * with ===, so the id must come back as the same type the payload carries.
 */
export function trackIdFrom(raw) {
    const numeric = Number(raw);

    return raw !== '' && Number.isInteger(numeric) ? numeric : raw;
}

export function loadImage(url) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}
