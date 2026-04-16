declare global {
    interface Map<K, V> {
        getOrInsertComputed(key: K, cb: (k: K) => V): V;
    }
}

// Polyfill ES2025 Map.getOrInsertComputed — pdfjs relies on it, and older Electron/Chromium lacks it.
if (!Map.prototype.getOrInsertComputed) {
    Map.prototype.getOrInsertComputed = function (
        key: unknown,
        cb: (k: unknown) => unknown,
    ) {
        if (this.has(key)) return this.get(key);
        const v = cb(key);
        this.set(key, v);
        return v;
    };
}

import * as pdfjsLib from 'pdfjs-dist';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.mjs',
    import.meta.url,
).toString();

export { pdfjsLib };

const DEFAULT_LONG_EDGE = 2560;
const MAX_CANVAS_DIMENSION = 4000;

export async function renderPdfFirstPageToPng(
    file: File,
    targetLongEdge: number = DEFAULT_LONG_EDGE,
): Promise<File> {
    const buffer = await file.arrayBuffer();
    const doc = await pdfjsLib.getDocument({ data: buffer }).promise;
    const canvas = document.createElement('canvas');

    try {
        const page = await doc.getPage(1);
        const baseViewport = page.getViewport({ scale: 1 });
        const longEdgeAt1x = Math.max(baseViewport.width, baseViewport.height);
        const requestedScale = targetLongEdge / longEdgeAt1x;
        const maxScale = MAX_CANVAS_DIMENSION / longEdgeAt1x;
        const scale = Math.min(requestedScale, maxScale);
        const viewport = page.getViewport({ scale });

        canvas.width = Math.round(viewport.width);
        canvas.height = Math.round(viewport.height);
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('Could not acquire 2D canvas context');
        }

        await page.render({ canvasContext: ctx, viewport, canvas }).promise;

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/png'),
        );
        if (!blob) {
            throw new Error('Canvas did not produce a PNG blob');
        }

        const pngName = file.name.replace(/\.pdf$/i, '.png');
        return new File([blob], pngName, { type: 'image/png' });
    } finally {
        // Zeroing canvas dimensions releases GPU-backed memory before the caller proceeds with the upload.
        canvas.width = 0;
        canvas.height = 0;
        await doc.destroy();
    }
}
