export async function getVideoDuration(file) {
    return new Promise((resolve) => {
        try {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            const url = URL.createObjectURL(file);
            const cleanup = () => {
                try { URL.revokeObjectURL(url); } catch (_) {}
            };
            video.onloadedmetadata = () => {
                const duration = Number.isFinite(video.duration) ? video.duration : 0;
                cleanup();
                resolve(duration);
            };
            video.onerror = () => {
                cleanup();
                resolve(0);
            };
            video.src = url;
        } catch (_) {
            resolve(0);
        }
    });
}

export function formatDuration(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

export async function getVideoMeta(file) {
    return new Promise((resolve) => {
        try {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            const url = URL.createObjectURL(file);
            const cleanup = () => {
                try { URL.revokeObjectURL(url); } catch (_) {}
            };
            video.onloadedmetadata = () => {
                resolve({
                    duration: Number.isFinite(video.duration) ? video.duration : 0,
                    width: video.videoWidth || 0,
                    height: video.videoHeight || 0
                });
                cleanup();
            };
            video.onerror = () => {
                cleanup();
                resolve({ duration: 0, width: 0, height: 0 });
            };
            video.src = url;
        } catch (_) {
            resolve({ duration: 0, width: 0, height: 0 });
        }
    });
}

function pickMediaRecorderMime() {
    const candidates = [
        'video/mp4;codecs="avc1.42E01E,mp4a.40.2"',
        'video/webm;codecs=vp8,opus',
        'video/webm;codecs=vp9,opus',
        'video/webm',
    ];
    for (const type of candidates) {
        if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) {
            return type;
        }
    }
    return '';
}

export async function trimVideoToDuration(file, maxSeconds, opts = {}) {
    const { videoBitsPerSecond = 700000, audioBitsPerSecond = 96000 } = opts;
    return new Promise((resolve, reject) => {
        try {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            const url = URL.createObjectURL(file);
            const cleanup = () => {
                try { URL.revokeObjectURL(url); } catch (_) {}
            };
            video.onloadedmetadata = async () => {
                const duration = Number.isFinite(video.duration) ? video.duration : 0;
                const sliceSeconds = Math.min(maxSeconds, duration || maxSeconds);
                if (typeof video.captureStream !== 'function') {
                    cleanup();
                    reject(new Error('captureStream indisponível'));
                    return;
                }
                const stream = video.captureStream();
                const mimeType = pickMediaRecorderMime();
                const recorder = new MediaRecorder(stream, {
                    mimeType: mimeType || undefined,
                    videoBitsPerSecond,
                    audioBitsPerSecond
                });
                const chunks = [];
                recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size) chunks.push(e.data);
                };
                recorder.onstop = () => {
                    cleanup();
                    const blob = new Blob(chunks, { type: recorder.mimeType || 'video/webm' });
                    resolve(blob);
                };
                recorder.onerror = (err) => {
                    cleanup();
                    reject(err);
                };
                recorder.start(200);
                try { await video.play(); } catch (_) {}
                setTimeout(() => {
                    try { recorder.stop(); } catch (_) {}
                    try { video.pause(); } catch (_) {}
                    stream.getTracks().forEach((t) => t.stop());
                }, Math.max(1, Math.floor(sliceSeconds * 1000)));
            };
            video.onerror = (err) => {
                cleanup();
                reject(err || new Error('Falha ao carregar vídeo'));
            };
            video.src = url;
        } catch (err) {
            reject(err);
        }
    });
}
