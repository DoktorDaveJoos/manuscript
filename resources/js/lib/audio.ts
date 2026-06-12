/**
 * Convert a MediaRecorder blob (webm/opus) into the 16 kHz mono PCM16 WAV
 * that whisper.cpp expects. Decoding and resampling happen in the renderer
 * via WebAudio, which keeps ffmpeg out of the app entirely.
 */
export async function blobToWav16kMono(blob: Blob): Promise<Blob> {
    const arrayBuffer = await blob.arrayBuffer();

    const decodeContext = new AudioContext();
    let decoded: AudioBuffer;
    try {
        decoded = await decodeContext.decodeAudioData(arrayBuffer);
    } finally {
        void decodeContext.close();
    }

    const targetRate = 16000;
    const offline = new OfflineAudioContext(
        1,
        Math.max(1, Math.ceil(decoded.duration * targetRate)),
        targetRate,
    );
    const source = offline.createBufferSource();
    source.buffer = decoded;
    source.connect(offline.destination);
    source.start();
    const rendered = await offline.startRendering();

    return encodeWavPcm16(rendered.getChannelData(0), targetRate);
}

function encodeWavPcm16(samples: Float32Array, sampleRate: number): Blob {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);

    writeAscii(view, 0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    writeAscii(view, 8, 'WAVE');
    writeAscii(view, 12, 'fmt ');
    view.setUint32(16, 16, true); // fmt chunk size
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, 1, true); // mono
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true); // byte rate
    view.setUint16(32, 2, true); // block align
    view.setUint16(34, 16, true); // bits per sample
    writeAscii(view, 36, 'data');
    view.setUint32(40, samples.length * 2, true);

    let offset = 44;
    for (let i = 0; i < samples.length; i++, offset += 2) {
        const sample = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(
            offset,
            sample < 0 ? sample * 0x8000 : sample * 0x7fff,
            true,
        );
    }

    return new Blob([buffer], { type: 'audio/wav' });
}

function writeAscii(view: DataView, offset: number, text: string): void {
    for (let i = 0; i < text.length; i++) {
        view.setUint8(offset + i, text.charCodeAt(i));
    }
}
