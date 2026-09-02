// Draws aggregated entry/exit zones from every completed night over the most
// recent reference photo. Zone intensity and label size scale with how many
// sightings used that spot.
const root = document.getElementById('heatmap-app');

if (root !== null) {
    initHeatmap(root);
}

async function initHeatmap(root) {
    const data = JSON.parse(document.getElementById('heatmap-data').textContent);
    const canvas = root.querySelector('[data-heatmap="canvas"]');
    const ctx = canvas.getContext('2d');

    const referenceImage = data.referenceImageUrl !== null ? await loadImage(data.referenceImageUrl) : null;

    canvas.width = data.frameWidth || referenceImage?.naturalWidth || 1280;
    canvas.height = data.frameHeight || referenceImage?.naturalHeight || 720;

    if (referenceImage !== null) {
        ctx.drawImage(referenceImage, 0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    } else {
        ctx.fillStyle = '#18181b';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    const maxCount = Math.max(
        1,
        ...data.entryZones.map((zone) => zone.count),
        ...data.exitZones.map((zone) => zone.count),
    );

    // Exit bars sit inset from the edge so overlapping entry/exit zones stay visible.
    for (const zone of data.exitZones) {
        drawZone(ctx, canvas, zone, maxCount, { color: '248, 113, 113', inset: 16, labelOffset: 64 });
    }

    for (const zone of data.entryZones) {
        drawZone(ctx, canvas, zone, maxCount, { color: '74, 222, 128', inset: 0, labelOffset: 34 });
    }
}

function drawZone(ctx, canvas, zone, maxCount, { color, inset, labelOffset }) {
    const intensity = 0.35 + 0.5 * (zone.count / maxCount);
    const thickness = 14;
    const horizontal = zone.edge === 'top' || zone.edge === 'bottom';

    const [x, y, width, height] = horizontal
        ? [zone.from, zone.edge === 'top' ? inset : canvas.height - thickness - inset, zone.to - zone.from, thickness]
        : [zone.edge === 'left' ? inset : canvas.width - thickness - inset, zone.from, thickness, zone.to - zone.from];

    ctx.fillStyle = `rgba(${color}, ${intensity})`;
    ctx.fillRect(x, y, width, height);

    // Count badge pulled inward from the middle of the zone.
    const centerAlongAxis = (zone.from + zone.to) / 2;
    const [labelX, labelY] = {
        top: [centerAlongAxis, labelOffset],
        bottom: [centerAlongAxis, canvas.height - labelOffset],
        left: [labelOffset, centerAlongAxis],
        right: [canvas.width - labelOffset, centerAlongAxis],
    }[zone.edge];

    const radius = 13 + 6 * (zone.count / maxCount);

    ctx.beginPath();
    ctx.arc(labelX, labelY, radius, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(${color}, 0.9)`;
    ctx.fill();

    ctx.fillStyle = '#18181b';
    ctx.font = `bold ${Math.round(radius)}px system-ui, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(String(zone.count), labelX, labelY);
}

function loadImage(url) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}
