import Alpine from 'alpinejs';

const CROP_MIN_SIZE = 0.05;

/** Regler 0–60; darüber würde es schnell überzeichnet wirken. */
const SHARPEN_SLIDER_MAX = 60;
/** Harte Obergrenze der effektiven Schärfstärke (unabhängig vom Regler). */
const SHARPEN_STRENGTH_CAP = 0.46;

/** Slider → begrenzte Schärfstärke (weiche Kurve, kein „Überschärfen“ am Anschlag). */
function effectiveSharpenStrength(sliderValue) {
    if (sliderValue <= 0) {
        return 0;
    }

    const clamped = Math.min(Math.max(0, sliderValue), SHARPEN_SLIDER_MAX);
    const t = clamped / SHARPEN_SLIDER_MAX;

    return SHARPEN_STRENGTH_CAP * t * t;
}

/** Unsharp-Maske (3×3) auf Canvas-Pixel. */
function applyCanvasSharpen(ctx, width, height, sliderValue) {
    const strength = effectiveSharpenStrength(sliderValue);
    if (strength <= 0 || width < 3 || height < 3) {
        return;
    }

    const imageData = ctx.getImageData(0, 0, width, height);
    const src = imageData.data;
    const w = width;
    const h = height;
    const out = new Uint8ClampedArray(src.length);
    out.set(src);

    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const idx = (y * w + x) * 4;
            for (let c = 0; c < 3; c++) {
                const i = idx + c;
                const center = src[i];
                const neighbors = src[i - w * 4] + src[i + w * 4] + src[i - 4] + src[i + 4];
                const sharpened = center + strength * (4 * center - neighbors);
                out[i] = sharpened < 0 ? 0 : sharpened > 255 ? 255 : sharpened;
            }
        }
    }

    imageData.data.set(out);
    ctx.putImageData(imageData, 0, 0);
}

const EDITOR_SOURCE_CACHE = 'ekn-admin-editor-source-v1';
const editorSourceBlobMemory = new Map();

async function fetchEditorSourceBlob(url, { crossOrigin = false, cacheKey = null } = {}) {
    if (!url) {
        throw new Error('no url');
    }

    const storeKey = cacheKey || url;
    const cached = editorSourceBlobMemory.get(storeKey);
    if (cached instanceof Blob) {
        return cached;
    }

    if (typeof caches !== 'undefined' && cacheKey) {
        try {
            const cache = await caches.open(EDITOR_SOURCE_CACHE);
            const hit = await cache.match(cacheKey);
            if (hit?.ok) {
                const blob = await hit.blob();
                editorSourceBlobMemory.set(storeKey, blob);

                return blob;
            }
        } catch {
            // Cache API optional (Quota, Privatmodus)
        }
    }

    const headers = { Accept: 'image/*' };
    if (!crossOrigin) {
        headers['X-Requested-With'] = 'XMLHttpRequest';
    }

    const response = await fetch(url, {
        mode: crossOrigin ? 'cors' : 'same-origin',
        credentials: crossOrigin ? 'omit' : 'same-origin',
        headers,
    });
    if (!response.ok) {
        throw new Error('fetch failed');
    }

    const blob = await response.blob();
    editorSourceBlobMemory.set(storeKey, blob);

    if (typeof caches !== 'undefined' && response.status === 200 && cacheKey) {
        try {
            const cache = await caches.open(EDITOR_SOURCE_CACHE);
            await cache.put(cacheKey, new Response(blob.slice(0), { headers: response.headers }));
        } catch {
            // Quota überschritten – Speicher-Cache reicht für die Session
        }
    }

    return blob;
}

async function loadEditorSourceBlob({ direct, proxy, cacheKey }) {
    if (!cacheKey) {
        throw new Error('no cache key');
    }

    const cached = editorSourceBlobMemory.get(cacheKey);
    if (cached instanceof Blob) {
        return cached;
    }

    if (direct) {
        try {
            return await fetchEditorSourceBlob(direct, { crossOrigin: true, cacheKey });
        } catch {
            // CORS oder Netzwerk – Fallback auf Same-Origin-Proxy
        }
    }

    if (proxy) {
        return fetchEditorSourceBlob(proxy, { crossOrigin: false, cacheKey });
    }

    throw new Error('no source url');
}

function prefetchEditorSource(spec) {
    if (!spec?.cacheKey || editorSourceBlobMemory.has(spec.cacheKey)) {
        return;
    }

    loadEditorSourceBlob(spec).catch(() => {});
}

async function invalidateEditorSourceCache(cacheKey) {
    if (!cacheKey) {
        return;
    }

    editorSourceBlobMemory.delete(cacheKey);

    if (typeof caches === 'undefined') {
        return;
    }

    try {
        const cache = await caches.open(EDITOR_SOURCE_CACHE);
        await cache.delete(cacheKey);
    } catch {
        // ignore
    }
}

function decodeEditorImage(blob, objectUrlToRevoke = null) {
    const objectUrl = URL.createObjectURL(blob);

    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            if (objectUrlToRevoke) {
                URL.revokeObjectURL(objectUrlToRevoke);
            }
            resolve({ img, objectUrl });
        };
        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            if (objectUrlToRevoke) {
                URL.revokeObjectURL(objectUrlToRevoke);
            }
            reject(new Error('decode failed'));
        };
        img.src = objectUrl;
    });
}

Alpine.data('adminImageEditor', (config = {}) => ({
    mediaId: config.mediaId || null,
    editorSourceUrl: config.editorSourceUrl || '',
    editorDirectSourceUrl: config.editorDirectSourceUrl || '',
    editorSourceCacheKey: config.editorSourceCacheKey || '',
    prefetchEditorSources: config.prefetchEditorSources || [],
    editorSaveUrl: config.editorSaveUrl || '',
    originalName: config.originalName || 'bild.jpg',
    redirectUrlTemplate: config.redirectUrlTemplate || '',

    editorSaving: false,
    editorMessage: '',
    editorError: false,
    editorDisplayReady: false,
    editorCropDragging: false,
    editorAspect: 'free',
    editorAspectFlipped: false,
    editorRotation: 0,
    editorStraighten: 0,
    editorBrightness: 100,
    editorContrast: 100,
    editorSaturation: 100,
    editorHue: 0,
    editorSharpen: 0,
    editorSharpenSliderMax: SHARPEN_SLIDER_MAX,
    editorQuality: 92,
    editorCrop: { x: 0, y: 0, w: 1, h: 1 },
    cropHandles: ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'],
    _editorImg: null,
    _editorObjectUrl: null,
    _editorDisplaySize: null,
    _editorResizeTimer: null,
    _stageResizeObserver: null,
    _cropDrag: null,
    _boundCropMove: null,
    _boundCropUp: null,
    editorStagePadding: 16,
    editorAspectOptions: [
        { id: 'free', label: 'Frei', ratio: null },
        { id: '1:1', label: '1:1', ratio: 1 },
        { id: '4:3', label: '4:3', ratio: 4 / 3 },
        { id: '3:2', label: '3:2', ratio: 3 / 2 },
        { id: '16:9', label: '16:9', ratio: 16 / 9 },
        { id: '4:5', label: '4:5', ratio: 4 / 5 },
        { id: '16:10', label: '16:10', ratio: 16 / 10 },
        { id: '21:9', label: '21:9', ratio: 21 / 9 },
        { id: '9:16', label: '9:16', ratio: 9 / 16 },
    ],

    init() {
        this._boundCropMove = (e) => this.onCropPointerMove(e);
        this._boundCropUp = (e) => this.onCropPointerUp(e);
        this.loadEditorImage();
        this.prefetchEditorSources.forEach((spec) => prefetchEditorSource(spec));
        this.$nextTick(() => this.observeStageResize());
        window.addEventListener('resize', () => {
            if (!this._editorImg) {
                return;
            }

            clearTimeout(this._editorResizeTimer);
            this._editorResizeTimer = setTimeout(() => {
                this.applyCanvasDisplaySize();
                this.renderEditorCanvas();
            }, 150);
        });
    },

    observeStageResize() {
        const view = this.$refs.editorStageView;
        if (!(view instanceof HTMLElement) || this._stageResizeObserver) {
            return;
        }

        this._stageResizeObserver = new ResizeObserver(() => {
            if (!this._editorImg) {
                return;
            }

            clearTimeout(this._editorResizeTimer);
            this._editorResizeTimer = setTimeout(() => this.renderEditorCanvas(), 80);
        });
        this._stageResizeObserver.observe(view);
    },

    getStageViewSize() {
        const view = this.$refs.editorStageView;
        if (!(view instanceof HTMLElement)) {
            return null;
        }

        const rect = view.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) {
            return null;
        }

        const pad = this.editorStagePadding;

        return {
            width: Math.max(1, Math.floor(rect.width - pad)),
            height: Math.max(1, Math.floor(rect.height - pad)),
        };
    },

    /** Canvas-Pixel und CSS-Anzeige: Bild füllt die verfügbare Fläche maximal aus. */
    computeEditorLayout(iw, ih) {
        const stage = this.getStageViewSize();
        if (!stage) {
            const legacyScale = Math.min(1, 1200 / Math.max(iw, ih));
            const cw = Math.max(1, Math.round(iw * legacyScale));
            const ch = Math.max(1, Math.round(ih * legacyScale));

            return { cw, ch, displayW: cw, displayH: ch };
        }

        const fitScale = Math.min(stage.width / iw, stage.height / ih);
        const displayW = Math.max(1, Math.round(iw * fitScale));
        const displayH = Math.max(1, Math.round(ih * fitScale));
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const maxPixelSide = 2560;
        let cw = Math.max(1, Math.round(displayW * dpr));
        let ch = Math.max(1, Math.round(displayH * dpr));

        if (iw >= displayW && ih >= displayH) {
            cw = Math.min(cw, iw);
            ch = Math.min(ch, ih);
        }

        if (Math.max(cw, ch) > maxPixelSide) {
            const cap = maxPixelSide / Math.max(cw, ch);
            cw = Math.max(1, Math.round(cw * cap));
            ch = Math.max(1, Math.round(ch * cap));
        }

        return { cw, ch, displayW, displayH };
    },

    csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta instanceof HTMLMetaElement ? meta.content : '';
    },

    clampEditorSharpen() {
        if (this.editorSharpen > SHARPEN_SLIDER_MAX) {
            this.editorSharpen = SHARPEN_SLIDER_MAX;
        }
        if (this.editorSharpen < 0) {
            this.editorSharpen = 0;
        }
    },

    editorSharpenWarning() {
        return this.editorSharpen > 40;
    },

    getActiveAspectRatio() {
        const opt = this.editorAspectOptions.find((o) => o.id === this.editorAspect);
        let ratio = opt?.ratio ?? null;
        if (ratio && this.editorAspectFlipped) {
            ratio = 1 / ratio;
        }

        return ratio;
    },

    /** Normierte Crop-Höhe bei gegebenem w (Seitenverhältnis auf Bildpixel bezogen). */
    cropHeightForWidth(w) {
        const img = this._editorImg;
        const ratio = this.getActiveAspectRatio();
        if (!img?.naturalWidth || !ratio) {
            return w;
        }

        return (w * img.naturalWidth) / (ratio * img.naturalHeight);
    },

    cropWidthForHeight(h) {
        const img = this._editorImg;
        const ratio = this.getActiveAspectRatio();
        if (!img?.naturalWidth || !ratio) {
            return h;
        }

        return (h * ratio * img.naturalHeight) / img.naturalWidth;
    },

    clampCrop(crop) {
        let { x, y, w, h } = crop;
        const ratio = this.getActiveAspectRatio();

        w = Math.max(CROP_MIN_SIZE, Math.min(1, w));
        h = Math.max(CROP_MIN_SIZE, Math.min(1, h));

        if (ratio) {
            h = this.cropHeightForWidth(w);
            if (h > 1) {
                h = 1;
                w = this.cropWidthForHeight(h);
            }
            if (h < CROP_MIN_SIZE) {
                h = CROP_MIN_SIZE;
                w = this.cropWidthForHeight(h);
            }
        }

        w = Math.max(CROP_MIN_SIZE, Math.min(1, w));
        h = Math.max(CROP_MIN_SIZE, Math.min(1, h));
        x = Math.max(0, Math.min(1 - w, x));
        y = Math.max(0, Math.min(1 - h, y));

        return { x, y, w, h };
    },

    cropBoxScreenStyle() {
        const crop = this.editorCrop;

        return {
            left: `${crop.x * 100}%`,
            top: `${crop.y * 100}%`,
            width: `${crop.w * 100}%`,
            height: `${crop.h * 100}%`,
        };
    },

    resetEditor(render = true) {
        this.editorAspect = 'free';
        this.editorAspectFlipped = false;
        this.editorRotation = 0;
        this.editorStraighten = 0;
        this.editorBrightness = 100;
        this.editorContrast = 100;
        this.editorSaturation = 100;
        this.editorHue = 0;
        this.editorSharpen = 0;
        this.editorQuality = 92;
        this.editorCrop = { x: 0, y: 0, w: 1, h: 1 };
        if (render) {
            this.renderEditorCanvas();
        }
    },

    setEditorAspect(id) {
        if (this.editorAspect === id) {
            this.editorAspectFlipped = !this.editorAspectFlipped;
        } else {
            this.editorAspect = id;
            this.editorAspectFlipped = false;
        }
        this.applyEditorAspectCrop();
        this.renderEditorCanvas();
    },

    applyEditorAspectCrop() {
        const ratio = this.getActiveAspectRatio();
        if (!ratio || !this._editorImg) {
            this.editorCrop = { x: 0, y: 0, w: 1, h: 1 };

            return;
        }

        const iw = this._editorImg.naturalWidth;
        const ih = this._editorImg.naturalHeight;
        const imageRatio = iw / ih;
        let cropW = 1;
        let cropH = 1;
        if (imageRatio > ratio) {
            cropW = ratio / imageRatio;
        } else {
            cropH = imageRatio / ratio;
        }
        this.editorCrop = this.clampCrop({
            x: (1 - cropW) / 2,
            y: (1 - cropH) / 2,
            w: cropW,
            h: cropH,
        });
    },

    rotateEditor(deg) {
        this.editorRotation = (this.editorRotation + deg) % 360;
        if (this.editorRotation < 0) {
            this.editorRotation += 360;
        }
        this.renderEditorCanvas();
    },

    startCropDrag(event, handle) {
        if (!this.editorDisplayReady || !(event instanceof PointerEvent)) {
            return;
        }

        this.editorCropDragging = true;
        this._cropDrag = {
            handle,
            startX: event.clientX,
            startY: event.clientY,
            startCrop: { ...this.editorCrop },
        };

        window.addEventListener('pointermove', this._boundCropMove);
        window.addEventListener('pointerup', this._boundCropUp);
        window.addEventListener('pointercancel', this._boundCropUp);
    },

    onCropPointerMove(event) {
        if (!this._cropDrag || !(event instanceof PointerEvent)) {
            return;
        }

        const wrap = this.$refs.editorCanvasWrap;
        const canvas = this.$refs.editorCanvas;
        if (!(wrap instanceof HTMLElement) || !(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) {
            return;
        }

        const dx = (event.clientX - this._cropDrag.startX) / rect.width;
        const dy = (event.clientY - this._cropDrag.startY) / rect.height;
        const start = this._cropDrag.startCrop;
        const handle = this._cropDrag.handle;
        const ratio = this.getActiveAspectRatio();

        let next = { ...start };

        if (handle === 'move') {
            next.x = start.x + dx;
            next.y = start.y + dy;
        } else {
            let x1 = start.x;
            let y1 = start.y;
            let x2 = start.x + start.w;
            let y2 = start.y + start.h;

            if (handle.includes('w')) {
                x1 = start.x + dx;
            }
            if (handle.includes('e')) {
                x2 = start.x + start.w + dx;
            }
            if (handle.includes('n')) {
                y1 = start.y + dy;
            }
            if (handle.includes('s')) {
                y2 = start.y + start.h + dy;
            }

            if (x2 < x1) {
                [x1, x2] = [x2, x1];
            }
            if (y2 < y1) {
                [y1, y2] = [y2, y1];
            }

            let w = x2 - x1;
            let h = y2 - y1;

            if (ratio) {
                const pixelRatio = (w * this._editorImg.naturalWidth) / (h * this._editorImg.naturalHeight);
                if (pixelRatio > ratio) {
                    w = (h * ratio * this._editorImg.naturalHeight) / this._editorImg.naturalWidth;
                    if (handle.includes('w') && !handle.includes('e')) {
                        x1 = x2 - w;
                    } else {
                        x2 = x1 + w;
                    }
                } else {
                    h = (w * this._editorImg.naturalWidth) / (ratio * this._editorImg.naturalHeight);
                    if (handle.includes('n') && !handle.includes('s')) {
                        y1 = y2 - h;
                    } else {
                        y2 = y1 + h;
                    }
                }
                w = x2 - x1;
                h = y2 - y1;
            }

            next = { x: x1, y: y1, w, h };
        }

        this.editorCrop = this.clampCrop(next);
        this.renderEditorCanvas();
    },

    onCropPointerUp() {
        this.editorCropDragging = false;
        this._cropDrag = null;
        window.removeEventListener('pointermove', this._boundCropMove);
        window.removeEventListener('pointerup', this._boundCropUp);
        window.removeEventListener('pointercancel', this._boundCropUp);
    },

    async loadEditorImage() {
        const sourceSpec = {
            direct: this.editorDirectSourceUrl || null,
            proxy: this.editorSourceUrl || null,
            cacheKey: this.editorSourceCacheKey || (this.mediaId ? `editor-media-${this.mediaId}` : ''),
        };
        if (!sourceSpec.proxy && !sourceSpec.direct) {
            this.editorError = true;
            this.editorMessage = 'Bild konnte für den Editor nicht geladen werden.';

            return;
        }

        this.editorError = false;
        this.editorMessage = '';
        this.editorDisplayReady = false;

        try {
            const blob = await loadEditorSourceBlob(sourceSpec);
            const { img, objectUrl } = await decodeEditorImage(blob, this._editorObjectUrl);
            this._editorObjectUrl = objectUrl;
            this._editorImg = img;
            this.applyEditorAspectCrop();
            this.$nextTick(() => {
                this.renderEditorCanvas();
                requestAnimationFrame(() => {
                    if (this.getStageViewSize()) {
                        this.renderEditorCanvas();
                    }
                });
            });
        } catch {
            this.editorError = true;
            this.editorMessage = 'Bild konnte für den Editor nicht geladen werden.';
        }
    },

    renderEditorCanvas() {
        const canvas = this.$refs.editorCanvas;
        const img = this._editorImg;
        if (!(canvas instanceof HTMLCanvasElement) || !img?.naturalWidth) {
            this.editorDisplayReady = false;

            return;
        }

        const iw = img.naturalWidth;
        const ih = img.naturalHeight;
        const { cw, ch, displayW, displayH } = this.computeEditorLayout(iw, ih);
        this._editorDisplaySize = { w: displayW, h: displayH };

        canvas.width = cw;
        canvas.height = ch;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            this.editorDisplayReady = false;

            return;
        }

        const crop = this.editorCrop;
        const cx = crop.x * cw;
        const cy = crop.y * ch;
        const cww = crop.w * cw;
        const chh = crop.h * ch;

        ctx.clearRect(0, 0, cw, ch);
        ctx.filter = `brightness(${this.editorBrightness}%) contrast(${this.editorContrast}%) saturate(${this.editorSaturation}%) hue-rotate(${this.editorHue}deg)`;
        ctx.drawImage(img, 0, 0, cw, ch);
        ctx.filter = 'none';
        applyCanvasSharpen(ctx, cw, ch, this.editorSharpen);

        ctx.fillStyle = 'rgba(0, 0, 0, 0.58)';
        ctx.fillRect(0, 0, cw, cy);
        ctx.fillRect(0, cy + chh, cw, ch - cy - chh);
        ctx.fillRect(0, cy, cx, chh);
        ctx.fillRect(cx + cww, cy, cw - cx - cww, chh);

        this.drawEditorGridOverlay(ctx, cww, chh, cx, cy);

        this.editorDisplayReady = true;
        this.$nextTick(() => {
            this.applyCanvasDisplaySize();
            this.renderRailPreview();
        });
    },

    applyCanvasDisplaySize() {
        const canvas = this.$refs.editorCanvas;
        const wrap = this.$refs.editorCanvasWrap;
        const img = this._editorImg;
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        let displayW;
        let displayH;
        const stage = this.getStageViewSize();

        if (stage && img?.naturalWidth) {
            const fitScale = Math.min(stage.width / img.naturalWidth, stage.height / img.naturalHeight);
            displayW = Math.max(1, Math.round(img.naturalWidth * fitScale));
            displayH = Math.max(1, Math.round(img.naturalHeight * fitScale));
            this._editorDisplaySize = { w: displayW, h: displayH };
        } else if (this._editorDisplaySize) {
            displayW = this._editorDisplaySize.w;
            displayH = this._editorDisplaySize.h;
        } else {
            displayW = canvas.width;
            displayH = canvas.height;
        }

        canvas.style.width = `${displayW}px`;
        canvas.style.height = `${displayH}px`;
        canvas.style.maxWidth = 'none';
        canvas.style.maxHeight = 'none';

        if (wrap instanceof HTMLElement) {
            wrap.style.width = `${displayW}px`;
            wrap.style.height = `${displayH}px`;
        }
    },

    /** Export-Vorschau (Crop + Filter + Drehung) für die linke Vorschau-Leiste. */
    getExportDimensions() {
        const img = this._editorImg;
        if (!img?.naturalWidth) {
            return null;
        }

        const crop = this.editorCrop;
        const sx = Math.round(crop.x * img.naturalWidth);
        const sy = Math.round(crop.y * img.naturalHeight);
        const sw = Math.max(1, Math.round(crop.w * img.naturalWidth));
        const sh = Math.max(1, Math.round(crop.h * img.naturalHeight));
        const rot = ((this.editorRotation % 360) + 360) % 360;
        const swap = rot === 90 || rot === 270;
        const outW = swap ? sh : sw;
        const outH = swap ? sw : sh;

        return { img, sx, sy, sw, sh, rot, swap, outW, outH };
    },

    paintExportPreview(ctx, dims, destW, destH) {
        const { img, sx, sy, sw, sh, rot, swap } = dims;
        ctx.clearRect(0, 0, destW, destH);
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, destW, destH);
        ctx.filter = `brightness(${this.editorBrightness}%) contrast(${this.editorContrast}%) saturate(${this.editorSaturation}%) hue-rotate(${this.editorHue}deg)`;
        ctx.save();
        ctx.translate(destW / 2, destH / 2);
        ctx.rotate(((rot + this.editorStraighten) * Math.PI) / 180);
        const drawW = swap ? destH : destW;
        const drawH = swap ? destW : destH;
        ctx.drawImage(img, sx, sy, sw, sh, -drawW / 2, -drawH / 2, drawW, drawH);
        ctx.restore();
        ctx.filter = 'none';
        applyCanvasSharpen(ctx, destW, destH, this.editorSharpen);
    },

    railPreviewMaxSide() {
        const canvas = this.$refs.editorRailPreview;
        const wrap = canvas?.parentElement;
        if (wrap instanceof HTMLElement && wrap.clientWidth > 0) {
            return Math.min(520, Math.max(220, wrap.clientWidth));
        }

        return 400;
    },

    renderRailPreview() {
        const canvas = this.$refs.editorRailPreview;
        const dims = this.getExportDimensions();
        if (!(canvas instanceof HTMLCanvasElement) || !dims) {
            return;
        }

        const maxSide = this.railPreviewMaxSide();
        const scale = Math.min(1, maxSide / Math.max(dims.outW, dims.outH));
        const pw = Math.max(1, Math.round(dims.outW * scale));
        const ph = Math.max(1, Math.round(dims.outH * scale));
        if (canvas.width !== pw || canvas.height !== ph) {
            canvas.width = pw;
            canvas.height = ph;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        this.paintExportPreview(ctx, dims, pw, ph);
    },

    drawEditorGridOverlay(ctx, width, height, offsetX = 0, offsetY = 0) {
        if (width < 3 || height < 3) {
            return;
        }

        const lines = [
            [offsetX + width / 3, offsetY, offsetX + width / 3, offsetY + height],
            [offsetX + (width * 2) / 3, offsetY, offsetX + (width * 2) / 3, offsetY + height],
            [offsetX, offsetY + height / 3, offsetX + width, offsetY + height / 3],
            [offsetX, offsetY + (height * 2) / 3, offsetX + width, offsetY + (height * 2) / 3],
        ];

        ctx.save();
        ctx.lineWidth = 1;
        ctx.setLineDash([]);

        ctx.strokeStyle = 'rgba(0, 0, 0, 0.35)';
        for (const [x1, y1, x2, y2] of lines) {
            ctx.beginPath();
            ctx.moveTo(x1 + 0.5, y1);
            ctx.lineTo(x2 + 0.5, y2);
            ctx.stroke();
        }

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.55)';
        for (const [x1, y1, x2, y2] of lines) {
            ctx.beginPath();
            ctx.moveTo(x1 + 0.5, y1);
            ctx.lineTo(x2 + 0.5, y2);
            ctx.stroke();
        }

        ctx.restore();
    },

    async exportEditorBlob() {
        const dims = this.getExportDimensions();
        if (!dims) {
            throw new Error('no image');
        }

        const canvas = document.createElement('canvas');
        canvas.width = dims.outW;
        canvas.height = dims.outH;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('no ctx');
        }

        this.paintExportPreview(ctx, dims, dims.outW, dims.outH);

        const quality = Math.min(1, Math.max(0.6, this.editorQuality / 100));

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('blob failed'));
                }
            }, 'image/jpeg', quality);
        });
    },

    async saveEditorAsNew() {
        if (!this.mediaId || this.editorSaving) {
            return;
        }
        this.editorSaving = true;
        this.editorMessage = '';
        this.editorError = false;
        try {
            const blob = await this.exportEditorBlob();
            const form = new FormData();
            const base = (this.originalName || 'bild').replace(/\.[^.]+$/, '');
            form.append('image', blob, `${base}-bearbeitet.jpg`);
            const response = await fetch(this.editorSaveUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                credentials: 'same-origin',
                body: form,
            });
            if (!response.ok) {
                throw new Error('save failed');
            }
            await invalidateEditorSourceCache(this.editorSourceCacheKey);
            const data = await response.json();
            const newId = data?.media_id;
            if (newId && this.redirectUrlTemplate) {
                window.location.href = this.redirectUrlTemplate.replace('__ID__', String(newId));

                return;
            }
            this.editorMessage = 'Neues Bild wurde gespeichert.';
        } catch {
            this.editorError = true;
            this.editorMessage = 'Speichern fehlgeschlagen.';
        } finally {
            this.editorSaving = false;
        }
    },
}));
