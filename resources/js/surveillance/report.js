import { formatClock } from './captureLogic.js';
import { Replay } from './replay.js';

const root = document.getElementById('report-app');

if (root !== null) {
    initReport(root);
}

async function initReport(root) {
    const el = (name) => root.querySelector(`[data-report="${name}"]`);

    const canvas = el('canvas');
    const playButton = el('play');
    const speedSelect = el('speed');
    const scrub = el('scrub');
    const clock = el('clock');
    const trailsToggle = el('trails');

    let replay = null;
    let referenceImage = null;
    let referenceImageUrl = null;

    const buildReplay = async () => {
        replay?.pause();

        const data = JSON.parse(document.getElementById('report-data').textContent);

        if (data.referenceImageUrl !== null && data.referenceImageUrl !== referenceImageUrl) {
            referenceImage = await loadImage(data.referenceImageUrl);
            referenceImageUrl = data.referenceImageUrl;
        }

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

    await buildReplay();

    // Dismissing/restoring a track re-renders the JSON island; rebuild the
    // canvas from the fresh payload once Livewire has morphed the DOM.
    onLivewireEvent('surveillance-report-updated', () => buildReplay());

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

    // Delegated so listeners survive Livewire morphing the table rows.
    root.addEventListener('click', (event) => {
        const row = event.target.closest('[data-track-id]');

        if (row === null || event.target.closest('button') !== null) {
            return;
        }

        const trackId = Number(row.dataset.trackId);
        replay.highlightedTrackId = replay.highlightedTrackId === trackId ? null : trackId;
        replay.draw();
    });
}

function onLivewireEvent(name, callback) {
    if (window.Livewire !== undefined) {
        window.Livewire.on(name, callback);
    } else {
        document.addEventListener('livewire:init', () => window.Livewire.on(name, callback));
    }
}

function loadImage(url) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}
