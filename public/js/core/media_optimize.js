export function formatBytes(bytes) {
    if (!Number.isFinite(bytes)) return 'N/A';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let idx = 0;
    while (size >= 1024 && idx < units.length - 1) {
        size /= 1024;
        idx += 1;
    }
    return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[idx]}`;
}

function readExifOrientation(buffer) {
    const view = new DataView(buffer);
    if (view.getUint16(0, false) !== 0xFFD8) return 1;
    let offset = 2;
    const length = view.byteLength;
    while (offset < length) {
        const marker = view.getUint16(offset, false);
        offset += 2;
        if (marker === 0xFFE1) {
            const exifLength = view.getUint16(offset, false);
            offset += 2;
            const exifHeader = offset;
            if (view.getUint32(exifHeader, false) !== 0x45786966) return 1;
            const tiffOffset = exifHeader + 6;
            const little = view.getUint16(tiffOffset, false) === 0x4949;
            const ifdOffset = view.getUint32(tiffOffset + 4, little);
            let dirStart = tiffOffset + ifdOffset;
            const entries = view.getUint16(dirStart, little);
            dirStart += 2;
            for (let i = 0; i < entries; i++) {
                const entryOffset = dirStart + i * 12;
                const tag = view.getUint16(entryOffset, little);
                if (tag === 0x0112) {
                    return view.getUint16(entryOffset + 8, little);
                }
            }
            return 1;
        }
        if ((marker & 0xFF00) !== 0xFF00) break;
        const size = view.getUint16(offset, false);
        offset += size;
    }
    return 1;
}

function applyExifTransform(ctx, orientation, width, height) {
    switch (orientation) {
        case 2:
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
            break;
        case 3:
            ctx.translate(width, height);
            ctx.rotate(Math.PI);
            break;
        case 4:
            ctx.translate(0, height);
            ctx.scale(1, -1);
            break;
        case 5:
            ctx.rotate(0.5 * Math.PI);
            ctx.scale(1, -1);
            break;
        case 6:
            ctx.rotate(0.5 * Math.PI);
            ctx.translate(0, -height);
            break;
        case 7:
            ctx.rotate(0.5 * Math.PI);
            ctx.translate(width, -height);
            ctx.scale(-1, 1);
            break;
        case 8:
            ctx.rotate(-0.5 * Math.PI);
            ctx.translate(-width, 0);
            break;
        default:
            break;
    }
}

async function loadBitmap(file) {
    if (typeof createImageBitmap === 'function') {
        return await createImageBitmap(file);
    }
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            resolve(img);
            try { URL.revokeObjectURL(img.src); } catch (_) {}
        };
        img.onerror = (err) => {
            try { URL.revokeObjectURL(img.src); } catch (_) {}
            reject(err);
        };
        img.src = URL.createObjectURL(file);
    });
}

function detectAlpha(ctx, width, height) {
    try {
        const data = ctx.getImageData(0, 0, width, height).data;
        const step = Math.max(1, Math.floor((width * height) / 12000));
        for (let i = 3; i < data.length; i += 4 * step) {
            if (data[i] < 250) return true;
        }
    } catch (_) {}
    return false;
}

async function supportsWebpEncoding() {
    try {
        const canvas = document.createElement('canvas');
        canvas.width = 2;
        canvas.height = 2;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, 2, 2);
        const dataUrl = canvas.toDataURL('image/webp');
        return dataUrl.startsWith('data:image/webp');
    } catch (_) {
        return false;
    }
}

async function canvasToBlob(canvas, mimeType, quality) {
    return new Promise((resolve) => {
        if (!canvas.toBlob) {
            resolve(null);
            return;
        }
        canvas.toBlob((blob) => resolve(blob || null), mimeType, quality);
    });
}

export async function optimizeImage(file, opts = {}) {
    const options = {
        maxDim: 1920,
        minDim: 1024,
        initialQuality: 0.88,
        minQuality: 0.45,
        qualityStep: 0.06,
        targetBytes: 14 * 1024 * 1024,
        onProgress: null,
        forceUltra: false,
        ...opts
    };

    const originalBytes = Number.isFinite(file?.size) ? file.size : 0;
    const originalType = file?.type || '';
    const isJpeg = originalType === 'image/jpeg' || originalType === 'image/jpg';
    const buffer = isJpeg ? await file.arrayBuffer() : null;
    const orientation = buffer ? readExifOrientation(buffer) : 1;

    if (options.forceUltra) {
        options.minQuality = Math.min(options.minQuality, 0.35);
        options.minDim = Math.min(options.minDim, 960);
        options.maxDim = Math.min(options.maxDim, 1280);
    }

    options.onProgress?.({ step: 1, total: 4, label: 'Lendo imagem...' });
    const bitmap = await loadBitmap(file);
    const baseW = bitmap.width || 1;
    const baseH = bitmap.height || 1;
    const orientedW = (orientation >= 5 && orientation <= 8) ? baseH : baseW;
    const orientedH = (orientation >= 5 && orientation <= 8) ? baseW : baseH;

    const orientedCanvas = document.createElement('canvas');
    orientedCanvas.width = orientedW;
    orientedCanvas.height = orientedH;
    const octx = orientedCanvas.getContext('2d', { alpha: true, willReadFrequently: true });
    applyExifTransform(octx, orientation, baseW, baseH);
    octx.drawImage(bitmap, 0, 0);

    const hasAlpha = detectAlpha(octx, orientedW, orientedH);
    const supportsWebp = await supportsWebpEncoding();
    const preferWebp = supportsWebp && (hasAlpha || options.preferWebp !== false);
    const formatCandidates = preferWebp ? ['image/webp', hasAlpha ? 'image/png' : 'image/jpeg'] : (hasAlpha ? ['image/png'] : ['image/jpeg']);

    const dimCandidates = [];
    const baseMax = options.maxDim || 1920;
    if (baseMax >= 1920) {
        dimCandidates.push(baseMax, 1600, 1280, 1024);
    } else {
        dimCandidates.push(baseMax, 1280, 1024);
    }
    const dims = dimCandidates.filter((d, idx) => d >= options.minDim && dimCandidates.indexOf(d) === idx);

    let best = null;
    let bestMeta = null;
    let progressStep = 2;

    for (const maxDim of dims) {
        options.onProgress?.({ step: progressStep, total: 4, label: `Redimensionando (${maxDim}px)...` });
        const scale = Math.min(1, maxDim / Math.max(orientedW, orientedH));
        const targetW = Math.max(1, Math.round(orientedW * scale));
        const targetH = Math.max(1, Math.round(orientedH * scale));
        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d', { alpha: true });
        ctx.drawImage(orientedCanvas, 0, 0, targetW, targetH);

        for (const mimeType of formatCandidates) {
            if (mimeType === 'image/png') {
                const blob = await canvasToBlob(canvas, mimeType);
                if (blob && (!best || blob.size < best.size)) {
                    best = blob;
                    bestMeta = { width: targetW, height: targetH, mimeType };
                }
                continue;
            }
            let quality = options.initialQuality;
            options.onProgress?.({ step: 3, total: 4, label: `Comprimindo (${Math.round(quality * 100)}%)...` });
            let blob = await canvasToBlob(canvas, mimeType, quality);
            if (!blob) continue;
            while (blob.size > options.targetBytes && quality > options.minQuality) {
                quality = Math.max(options.minQuality, quality - options.qualityStep);
                options.onProgress?.({ step: 3, total: 4, label: `Comprimindo (${Math.round(quality * 100)}%)...` });
                blob = await canvasToBlob(canvas, mimeType, quality);
                if (!blob) break;
            }
            if (blob && (!best || blob.size < best.size)) {
                best = blob;
                bestMeta = { width: targetW, height: targetH, mimeType };
            }
        }
        progressStep += 1;
    }

    options.onProgress?.({ step: 4, total: 4, label: 'Finalizando...' });
    if (!best || !bestMeta) {
        return {
            optimizedFile: file,
            stats: {
                originalBytes,
                optimizedBytes: originalBytes,
                width: baseW,
                height: baseH,
                format: originalType || 'unknown',
                hasAlpha
            }
        };
    }

    const baseName = (file.name || 'image').replace(/\.[^.]+$/, '');
    const ext = bestMeta.mimeType === 'image/webp' ? 'webp' : (bestMeta.mimeType === 'image/png' ? 'png' : 'jpg');
    const optimizedFile = new File([best], `${baseName}.${ext}`, { type: bestMeta.mimeType, lastModified: Date.now() });

    return {
        optimizedFile,
        stats: {
            originalBytes,
            optimizedBytes: best.size,
            width: bestMeta.width,
            height: bestMeta.height,
            format: bestMeta.mimeType,
            hasAlpha
        }
    };
}
