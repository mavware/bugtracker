// Renders trails and the animated replay over the reference photo. All
// coordinates in the data are full-frame pixels; the canvas is drawn at the
// frame's native resolution and scaled by CSS.
export class Replay {
    constructor({ canvas, data, referenceImage }) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.data = data;
        this.referenceImage = referenceImage;
        this.duration = Math.max(1, ...data.tracks.map((track) => track.endOffsetMs));
        this.playheadMs = null;
        this.playing = false;
        this.speed = 60;
        this.showTrails = true;
        this.highlightedTrackId = null;
        this.lastFrameTime = null;

        canvas.width = data.frameWidth || referenceImage?.naturalWidth || 1280;
        canvas.height = data.frameHeight || referenceImage?.naturalHeight || 720;

        this.draw();
    }

    trackColor(index, alpha = 1) {
        return `hsla(${(index * 67) % 360}, 85%, 60%, ${alpha})`;
    }

    draw() {
        const { ctx, canvas } = this;

        if (this.referenceImage !== null) {
            ctx.drawImage(this.referenceImage, 0, 0, canvas.width, canvas.height);
            ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        } else {
            ctx.fillStyle = '#18181b';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        this.data.tracks.forEach((track, index) => {
            const upTo = this.playheadMs === null ? Infinity : this.playheadMs;
            this.drawTrack(track, index, upTo);
        });

        this.drawZones();
    }

    drawTrack(track, index, upToMs) {
        const { ctx } = this;
        const visible = track.points.filter(([t]) => t <= upToMs);

        if (visible.length === 0) {
            return;
        }

        const highlighted = this.highlightedTrackId === track.id;
        const dimmed = this.highlightedTrackId !== null && !highlighted;

        if (this.showTrails || highlighted || this.playheadMs !== null) {
            ctx.beginPath();
            ctx.moveTo(visible[0][1], visible[0][2]);
            for (const [, x, y] of visible) {
                ctx.lineTo(x, y);
            }
            ctx.strokeStyle = this.trackColor(index, dimmed ? 0.15 : 0.9);
            ctx.lineWidth = highlighted ? 5 : 2.5;
            ctx.stroke();

            // Entry dot and exit arrow only once the track is fully drawn.
            if (upToMs >= track.endOffsetMs) {
                ctx.beginPath();
                ctx.arc(visible[0][1], visible[0][2], highlighted ? 9 : 6, 0, Math.PI * 2);
                ctx.fillStyle = this.trackColor(index, dimmed ? 0.15 : 1);
                ctx.fill();
                this.drawArrowhead(visible, index, dimmed, highlighted);
            }
        }

        // Bright head dot while replaying.
        if (this.playheadMs !== null && upToMs < track.endOffsetMs && visible.length > 0) {
            const [, x, y] = visible[visible.length - 1];
            ctx.beginPath();
            ctx.arc(x, y, 8, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = this.trackColor(index);
            ctx.fill();
        }
    }

    drawArrowhead(points, index, dimmed, highlighted) {
        if (points.length < 2) {
            return;
        }

        const { ctx } = this;
        const [, x2, y2] = points[points.length - 1];
        const [, x1, y1] = points[points.length - 2];
        const angle = Math.atan2(y2 - y1, x2 - x1);
        const size = highlighted ? 18 : 12;

        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - size * Math.cos(angle - 0.4), y2 - size * Math.sin(angle - 0.4));
        ctx.lineTo(x2 - size * Math.cos(angle + 0.4), y2 - size * Math.sin(angle + 0.4));
        ctx.closePath();
        ctx.fillStyle = this.trackColor(index, dimmed ? 0.15 : 1);
        ctx.fill();
    }

    drawZones() {
        const zones = [
            ...(this.data.analytics?.entry_zones ?? []).map((zone) => ({ ...zone, kind: 'entry' })),
            ...(this.data.analytics?.exit_zones ?? []).map((zone) => ({ ...zone, kind: 'exit' })),
        ];

        const { ctx } = this;

        for (const zone of zones) {
            const horizontal = zone.edge === 'top' || zone.edge === 'bottom';
            const thickness = 10;
            const [x, y, width, height] = horizontal
                ? [zone.from, zone.edge === 'top' ? 0 : this.canvas.height - thickness, zone.to - zone.from, thickness]
                : [zone.edge === 'left' ? 0 : this.canvas.width - thickness, zone.from, thickness, zone.to - zone.from];

            ctx.fillStyle = zone.kind === 'entry' ? 'rgba(74, 222, 128, 0.65)' : 'rgba(248, 113, 113, 0.65)';
            ctx.fillRect(x, y, width, height);
        }
    }

    play() {
        this.playing = true;
        this.playheadMs = this.playheadMs === null || this.playheadMs >= this.duration ? 0 : this.playheadMs;
        this.lastFrameTime = performance.now();
        requestAnimationFrame((time) => this.tick(time));
    }

    pause() {
        this.playing = false;
    }

    stopReplay() {
        this.playing = false;
        this.playheadMs = null;
        this.draw();
    }

    seek(fraction) {
        this.playheadMs = Math.round(fraction * this.duration);
        this.draw();
    }

    tick(time) {
        if (!this.playing) {
            return;
        }

        this.playheadMs += (time - this.lastFrameTime) * this.speed;
        this.lastFrameTime = time;

        if (this.playheadMs >= this.duration) {
            this.playheadMs = this.duration;
            this.playing = false;
        }

        this.draw();
        this.onFrame?.(this.playheadMs / this.duration);

        if (this.playing) {
            requestAnimationFrame((nextTime) => this.tick(nextTime));
        }
    }
}
