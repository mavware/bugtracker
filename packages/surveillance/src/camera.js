// Owns the camera stream and the two canvases: a full-resolution one for
// reference frames and crops, and a downscaled one for per-frame processing.
export class Camera {
    constructor(videoElement, processingWidth = 320) {
        this.video = videoElement;
        this.processingWidth = processingWidth;
        this.stream = null;
        this.fullCanvas = document.createElement('canvas');
        this.procCanvas = document.createElement('canvas');
        this.fullCtx = this.fullCanvas.getContext('2d', { willReadFrequently: true });
        this.procCtx = this.procCanvas.getContext('2d', { willReadFrequently: true });
    }

    async start() {
        this.stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'environment',
            },
            audio: false,
        });

        this.video.srcObject = this.stream;
        await this.video.play();

        this.frameWidth = this.video.videoWidth;
        this.frameHeight = this.video.videoHeight;
        this.fullCanvas.width = this.frameWidth;
        this.fullCanvas.height = this.frameHeight;
        this.procCanvas.width = this.processingWidth;
        this.procCanvas.height = Math.round(this.frameHeight * (this.processingWidth / this.frameWidth));
        this.scale = this.frameWidth / this.procCanvas.width;
    }

    stop() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    }

    grabProcessedFrame() {
        this.procCtx.drawImage(this.video, 0, 0, this.procCanvas.width, this.procCanvas.height);

        return this.procCtx.getImageData(0, 0, this.procCanvas.width, this.procCanvas.height);
    }

    async captureReferenceJpeg() {
        this.fullCtx.drawImage(this.video, 0, 0, this.frameWidth, this.frameHeight);

        return new Promise((resolve) => this.fullCanvas.toBlob(resolve, 'image/jpeg', 0.85));
    }

    // Capture a small crop around a full-frame position, returned as raw base64
    // (no data: prefix) so it can ride inline in a JSON payload.
    captureCropBase64(centerX, centerY, size = 48) {
        this.fullCtx.drawImage(this.video, 0, 0, this.frameWidth, this.frameHeight);

        const half = size / 2;
        const x = Math.max(0, Math.min(this.frameWidth - size, Math.round(centerX - half)));
        const y = Math.max(0, Math.min(this.frameHeight - size, Math.round(centerY - half)));

        const crop = document.createElement('canvas');
        crop.width = size;
        crop.height = size;
        crop.getContext('2d').drawImage(this.fullCanvas, x, y, size, size, 0, 0, size, size);

        return crop.toDataURL('image/jpeg', 0.7).split(',')[1];
    }
}
