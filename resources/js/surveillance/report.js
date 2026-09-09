import { loadImage, mountReportControls } from '@bugtracker/surveillance';

const root = document.getElementById('report-app');

if (root !== null && root.dataset.mode !== 'local') {
    initReport(root);
}

// The logged-in report: the payload is a JSON island the server renders, and
// re-renders whenever a track is dismissed or restored.
function initReport(root) {
    let referenceImage = null;
    let referenceImageUrl = null;

    const controls = mountReportControls(root, {
        loadData: async () => {
            const data = JSON.parse(document.getElementById('report-data').textContent);

            if (data.referenceImageUrl !== null && data.referenceImageUrl !== referenceImageUrl) {
                referenceImage = await loadImage(data.referenceImageUrl);
                referenceImageUrl = data.referenceImageUrl;
            }

            return { data, referenceImage };
        },
    });

    // Dismissing/restoring a track re-renders the JSON island; rebuild the
    // canvas from the fresh payload once Livewire has morphed the DOM.
    onLivewireEvent('surveillance-report-updated', () => controls.rebuild());
}

function onLivewireEvent(name, callback) {
    if (window.Livewire !== undefined) {
        window.Livewire.on(name, callback);
    } else {
        document.addEventListener('livewire:init', () => window.Livewire.on(name, callback));
    }
}
