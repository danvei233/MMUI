// fluid-bg.ts
// High-perf Canvas fluid background with dirty-rect partial redraw (TypeScript)

type RGB = [number, number, number];

export interface Center {
    x: number | string;
    y: number | string;
}

export interface Layer {
    color: RGB;
    centerAlpha?: number;
    midAlpha?: number;
    edgeAlpha?: number;
}

export interface Drift {
    ax?: number;
    ay?: number;
    sx?: number;
    sy?: number;
    speed?: number; // cycles per second fraction (smaller = slower)
}

export interface Breath {
    scale?: [number, number];
    opacity?: [number, number];
    speed?: number; // cycles per second
    phase?: number; // 0..1
}

export interface BlobConfig {
    id?: string;
    diameter: number | string; // px / '72vmax' / '40vw' / '30%' ...
    center: Center | ((dims: Dims) => { x: number; y: number });
    layers: Layer[];
    opacity?: number;
    parallaxScale?: number;
    drift?: Drift;
    breath?: Breath | null;
}

export interface FluidOptions {
    alpha?: number;                 // global alpha multiplier
    blurPx?: number;                // base blur radius (CSS px; internally × dpr)
    composite?: GlobalCompositeOperation;
    parallaxVmax?: number;          // parallax in "vmax" percentage basis (pixels = vMax/100 * this)
    fpsCap?: number;                // 0 = uncapped
    maxDpr?: number;                // cap devicePixelRatio
    autoStart?: boolean;

    // Dirty-rect tuning
    damagePaddingPx?: number;       // extra safety padding (CSS px)
    fullRedrawThreshold?: number;   // when covered area > threshold, fallback to full redraw
    maxDirtyRects?: number;         // when merged rects too many, fallback to full redraw

    // UX
    pointerTracking?: boolean;      // default true
}

type Dims = { w: number; h: number; vMax: number };
type Rect = { x: number; y: number; w: number; h: number };

type EaseFn = (t: number) => number;

const clamp = (v: number, a: number, b: number) => Math.min(b, Math.max(a, v));
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;
const TAU = Math.PI * 2;

const Easings: Record<string, EaseFn> = {
    easeInOutQuad: (t: number) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2),
};

function unitToPx(v: number | string, dims: Dims, axis: "x" | "y" | "vmax"): number {
    const { w, h, vMax } = dims;
    if (typeof v === "number") return v;
    const s = String(v).trim();
    if (s.endsWith("px")) return parseFloat(s);
    if (s.endsWith("%")) return (axis === "y" ? h : w) * (parseFloat(s) / 100);
    if (s.endsWith("vw")) return w * (parseFloat(s) / 100);
    if (s.endsWith("vh")) return h * (parseFloat(s) / 100);
    if (s.endsWith("vmax")) return vMax * (parseFloat(s) / 100);
    const n = parseFloat(s);
    return Number.isNaN(n) ? 0 : n;
}

function resolveCenter(center: BlobConfig["center"], dims: Dims): { x: number; y: number } {
    if (typeof center === "function") {
        const p = center(dims) || { x: 0, y: 0 };
        return { x: p.x, y: p.y };
    }
    return {
        x: unitToPx(center.x, dims, "x"),
        y: unitToPx(center.y, dims, "y"),
    };
}

// ----- dirty-rect helpers -----
function inflateRect(r: Rect, pad: number): Rect {
    return { x: r.x - pad, y: r.y - pad, w: r.w + pad * 2, h: r.h + pad * 2 };
}
function intersect(a: Rect, b: Rect): boolean {
    return !(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y);
}
function union(a: Rect, b: Rect): Rect {
    const x = Math.min(a.x, b.x);
    const y = Math.min(a.y, b.y);
    const r = Math.max(a.x + a.w, b.x + b.w);
    const btm = Math.max(a.y + a.h, b.y + b.h);
    return { x, y, w: r - x, h: btm - y };
}
/** light merge: union touching/overlapping rects (with small tolerance) */
function mergeRects(rects: Rect[], tolerance = 2): Rect[] {
    if (rects.length <= 1) return rects;
    const out: Rect[] = [];
    const work = rects.map((r) => inflateRect(r, tolerance));
    while (work.length) {
        let cur = work.pop()!;
        let merged = false;
        for (let i = 0; i < work.length; i++) {
            if (intersect(cur, work[i])) {
                cur = union(cur, work[i]);
                work.splice(i, 1);
                merged = true;
                i = -1;
            }
        }
        out.push(cur);
        if (merged) { /* keep iterating */ }
    }
    return out.map((r) => inflateRect(r, -tolerance));
}

type InternalEase = { from: { x: number; y: number }, to: { x: number; y: number }, t: number, durMs: number, ease: EaseFn };

type BlobInternal = {
    id: string;
    diameter: BlobConfig["diameter"];
    center: BlobConfig["center"];
    layers: Layer[];
    opacity: number;
    parallaxScale: number;
    drift: Required<Drift>;
    breath: Breath | null;

    _sprite: HTMLCanvasElement | null;
    _diamPx: number;
    _ease: InternalEase | null;
    _lastRect: Rect | null;
    _nextRect?: Rect | null;
    _static: boolean;
};

type State = {
    w: number;
    h: number;
    vMax: number;
    dpr: number;

    alpha: number;
    blurPx: number;
    composite: GlobalCompositeOperation;
    parallaxVmax: number;

    fpsCap: number;
    px: number; py: number;   // smoothed pointer (-1..1)
    tx: number; ty: number;   // target pointer
    running: boolean;
    prefersReduce: boolean;

    // dirty-rect tuning
    damagePaddingPx: number;
    fullRedrawThreshold: number;
    maxDirtyRects: number;
};

export class FluidBackground {
    private canvas: HTMLCanvasElement;
    private ctx: CanvasRenderingContext2D | null;

    private state: State;
    private _blobs = new Map<string, BlobInternal>();
    private _idSeq = 0;

    private _staticLayer: HTMLCanvasElement;
    private _staticCtx: CanvasRenderingContext2D | null;
    private _needRebuildStatic = true;

    private _raf = 0;
    private _lastFrameTs = 0; // for delta-time easing
    private _pointerEnabled = true;

    private _onResize: () => void;
    private _onPointerMove: (e: PointerEvent) => void;
    private _onVisibility: () => void;

    constructor(canvas: HTMLCanvasElement, opts: FluidOptions = {}) {
        if (!canvas) throw new Error("FluidBackground: canvas is required");
        this.canvas = canvas;
        this.ctx = canvas.getContext("2d", { alpha: true, desynchronized: true });

        const isMobile = typeof window !== "undefined" ? matchMedia("(max-width: 900px)").matches : false;
        const prefersReduce = typeof window !== "undefined" ? matchMedia("(prefers-reduced-motion: reduce)").matches : false;

        this.state = {
            w: 0, h: 0, vMax: 0,
            dpr: clamp((typeof window !== "undefined" ? window.devicePixelRatio || 1 : 1), 1, opts.maxDpr ?? 2),

            alpha: opts.alpha ?? (isMobile ? 0.44 : 0.5),
            blurPx: opts.blurPx ?? (isMobile ? 32 : 36),
            composite: opts.composite ?? "source-over",
            parallaxVmax: opts.parallaxVmax ?? (isMobile ? 0.6 : 0.9),
            fpsCap: opts.fpsCap ?? 0,

            px: 0, py: 0, tx: 0, ty: 0,
            running: false,
            prefersReduce,

            damagePaddingPx: opts.damagePaddingPx ?? 12,
            fullRedrawThreshold: clamp(opts.fullRedrawThreshold ?? 0.55, 0.1, 1.0),
            maxDirtyRects: opts.maxDirtyRects ?? 16,
        };

        // static layer (device-pixel sized)
        this._staticLayer = document.createElement("canvas");
        this._staticCtx = this._staticLayer.getContext("2d");

        this._onResize = () => this._resize();
        this._onPointerMove = (e: PointerEvent) => {
            if (!this._pointerEnabled) return;
            const vw = window.innerWidth || 1;
            const vh = window.innerHeight || 1;
            this.state.tx = (e.clientX / vw) * 2 - 1;
            this.state.ty = (e.clientY / vh) * 2 - 1;
        };
        this._onVisibility = () => {
            if (document.hidden) this.stop();
            else if (!this.state.prefersReduce) this.start();
        };

        window.addEventListener("resize", this._onResize);
        if (opts.pointerTracking !== false) {
            window.addEventListener("pointermove", this._onPointerMove, { passive: true });
            this._pointerEnabled = true;
        } else {
            this._pointerEnabled = false;
        }
        document.addEventListener("visibilitychange", this._onVisibility);

        this._resize();
        if (opts.autoStart ?? !prefersReduce) this.start();
        else this._fullRedraw(performance.now()); // paint a still frame
    }

    /** cleanup all listeners and resources */
    destroy(): void {
        this.stop();
        window.removeEventListener("resize", this._onResize);
        window.removeEventListener("pointermove", this._onPointerMove);
        document.removeEventListener("visibilitychange", this._onVisibility);
        this._blobs.clear();
        this.ctx = null;
        this._staticCtx = null;
    }

    start(): void {
        if (this.state.running || !this.ctx) return;
        this.state.running = true;
        this._lastFrameTs = performance.now();
        this._raf = requestAnimationFrame((ts) => this._loop(ts));
    }

    stop(): void {
        this.state.running = false;
        cancelAnimationFrame(this._raf);
    }

    setFpsCap(v: number): void { this.state.fpsCap = Math.max(0, v | 0); }
    setParallax(v: number): void { this.state.parallaxVmax = Math.max(0, Number(v) || 0); }
    setPointerTracking(enabled: boolean): void {
        if (enabled === this._pointerEnabled) return;
        this._pointerEnabled = enabled;
        if (enabled) window.addEventListener("pointermove", this._onPointerMove, { passive: true });
        else window.removeEventListener("pointermove", this._onPointerMove);
    }
    setBlurPx(px: number): void {
        this.state.blurPx = Math.max(0, px);
        this.rebuildAll();
    }
    setAlpha(a: number): void { this.state.alpha = clamp(a, 0, 1); }
    setComposite(op: GlobalCompositeOperation): void { this.state.composite = op; }

    /** add a blob and return its id */
    addBlob(cfg: BlobConfig): string {
        const id = cfg.id || `b${++this._idSeq}`;
        const blob: BlobInternal = {
            id,
            diameter: cfg.diameter,
            center: cfg.center,
            layers: cfg.layers || [],
            opacity: cfg.opacity ?? 1,
            parallaxScale: cfg.parallaxScale ?? 1,
            drift: {
                ax: cfg.drift?.ax ?? 2,
                ay: cfg.drift?.ay ?? 1,
                sx: cfg.drift?.sx ?? 1,
                sy: cfg.drift?.sy ?? 1,
                speed: cfg.drift?.speed ?? 1 / 40,
            },
            breath: cfg.breath ? {
                scale: cfg.breath.scale ?? [1, 1],
                opacity: cfg.breath.opacity ?? [1, 1],
                speed: cfg.breath.speed ?? 1 / 40,
                phase: cfg.breath.phase ?? 0,
            } : null,

            _sprite: null,
            _diamPx: 0,
            _ease: null,
            _lastRect: null,
            _static: false,
        };
        this._blobs.set(id, blob);
        this._rebuildSprite(blob);
        this._classifyBlobStatic(blob);
        this._needRebuildStatic = true;
        return id;
    }

    /** update properties; layers/diameter/blur changes trigger sprite rebuild automatically */
    updateBlob(id: string, patch: Partial<BlobConfig>): void {
        const b = this._blobs.get(id);
        if (!b) return;

        let needSprite = false;
        let affectsStatic = false;

        if (patch.diameter !== undefined) { b.diameter = patch.diameter; needSprite = true; }
        if (patch.layers !== undefined) { b.layers = patch.layers; needSprite = true; }
        if (patch.center !== undefined) { b.center = patch.center; affectsStatic = true; }
        if (patch.opacity !== undefined) { b.opacity = patch.opacity ?? 1; affectsStatic = true; }
        if (patch.parallaxScale !== undefined) { b.parallaxScale = patch.parallaxScale ?? 1; affectsStatic = true; }

        if (patch.drift !== undefined) {
            const d = patch.drift || {};
            b.drift = {
                ax: d.ax ?? b.drift.ax,
                ay: d.ay ?? b.drift.ay,
                sx: d.sx ?? b.drift.sx,
                sy: d.sy ?? b.drift.sy,
                speed: d.speed ?? b.drift.speed,
            };
            affectsStatic = true;
        }

        if (patch.breath !== undefined) {
            b.breath = patch.breath ? {
                scale: patch.breath.scale ?? [1, 1],
                opacity: patch.breath.opacity ?? [1, 1],
                speed: patch.breath.speed ?? 1 / 40,
                phase: patch.breath.phase ?? 0,
            } : null;
            affectsStatic = true;
        }

        if (needSprite) this._rebuildSprite(b);
        if (affectsStatic) this._classifyBlobStatic(b);
        if (needSprite || affectsStatic) this._needRebuildStatic = true;
    }

    removeBlob(id: string): void {
        const b = this._blobs.get(id);
        if (!b) return;
        this._blobs.delete(id);
        this._needRebuildStatic = true;
    }

    clearBlobs(): void {
        this._blobs.clear();
        this._needRebuildStatic = true;
    }

    /** smooth move a blob to a target CSS-pixel center */
    easeTo(id: string, toCenter: Center, durationMs = 800, easing: keyof typeof Easings = "easeInOutQuad"): void {
        const b = this._blobs.get(id);
        if (!b) return;
        const dims: Dims = { w: this.state.w, h: this.state.h, vMax: this.state.vMax };
        const from = resolveCenter(b.center, dims);
        const to = {
            x: unitToPx(toCenter.x, dims, "x"),
            y: unitToPx(toCenter.y, dims, "y"),
        };
        b._ease = { from, to, t: 0, durMs: Math.max(1, durationMs), ease: Easings[easing] || Easings.easeInOutQuad };
        if (b._static) { b._static = false; this._needRebuildStatic = true; }
    }

    /** rebuild all blob sprites (call after blurPx / DPR changes) */
    rebuildAll(): void {
        for (const b of this._blobs.values()) this._rebuildSprite(b);
        this._needRebuildStatic = true;
    }

    // ======== internals ========

    private _resize(): void {
        const { canvas, state } = this;
        const w = window.innerWidth;
        const h = window.innerHeight;
        state.w = w; state.h = h; state.vMax = Math.max(w, h);

        canvas.style.width = `${w}px`;
        canvas.style.height = `${h}px`;
        canvas.width = Math.round(w * state.dpr);
        canvas.height = Math.round(h * state.dpr);

        this._staticLayer.width = canvas.width;
        this._staticLayer.height = canvas.height;

        for (const b of this._blobs.values()) this._rebuildSprite(b);
        this._needRebuildStatic = true;
        this._fullRedraw(performance.now());
    }

    private _rebuildSprite(b: BlobInternal): void {
        const dims: Dims = { w: this.state.w, h: this.state.h, vMax: this.state.vMax };
        const dpx = unitToPx(b.diameter, dims, "vmax");
        b._diamPx = Math.max(32, Math.round(dpx));

        const pad = Math.ceil(this.state.blurPx * this.state.dpr) * 2;
        const size = Math.round(b._diamPx * this.state.dpr) + pad;

        const off = document.createElement("canvas");
        off.width = size; off.height = size;
        const o = off.getContext("2d")!;
        const cx = size / 2, cy = size / 2;
        const r = (b._diamPx * this.state.dpr) / 2;

        o.clearRect(0, 0, size, size);
        o.globalCompositeOperation = "lighter";
        o.filter = `blur(${this.state.blurPx * this.state.dpr}px)`;

        for (const layer of b.layers) {
            const c = layer.color;
            const ca = layer.centerAlpha ?? 0.6;
            const ma = layer.midAlpha ?? ca * 0.44;
            const ea = layer.edgeAlpha ?? 0.0;

            const g = o.createRadialGradient(cx, cy, 0, cx, cy, r * 0.95);
            g.addColorStop(0.00, `rgba(${c[0]},${c[1]},${c[2]},${ca})`);
            g.addColorStop(0.38, `rgba(${c[0]},${c[1]},${c[2]},${ma})`);
            g.addColorStop(0.74, `rgba(${c[0]},${c[1]},${c[2]},${ea})`);
            g.addColorStop(1.00, `rgba(${c[0]},${c[1]},${c[2]},0)`);

            o.beginPath(); o.arc(cx, cy, r, 0, TAU); o.closePath();
            o.fillStyle = g; o.fill();
        }
        o.filter = "none";
        o.globalCompositeOperation = "source-over";
        b._sprite = off;
    }

    private _classifyBlobStatic(b: BlobInternal): void {
        const noParallax = !b.parallaxScale || b.parallaxScale === 0;
        const noBreath = !b.breath || (
            (!b.breath.scale || (b.breath.scale[0] === 1 && b.breath.scale[1] === 1)) &&
            (!b.breath.opacity || (b.breath.opacity[0] === 1 && b.breath.opacity[1] === 1))
        );
        const d = b.drift || {};
        const noDrift = (!d.speed || d.speed === 0) && (!d.ax && !d.ay && !d.sx && !d.sy);
        const staticCandidate = noParallax && noBreath && noDrift && !b._ease;
        b._static = !!staticCandidate;
    }

    private _buildStaticLayer(): void {
        if (!this._staticCtx) return;
        const s = this.state;
        const sl = this._staticCtx;
        sl.clearRect(0, 0, this._staticLayer.width, this._staticLayer.height);

        for (const b of this._blobs.values()) {
            if (!b._static || !b._sprite) continue;
            const base = resolveCenter(b.center, { w: s.w, h: s.h, vMax: s.vMax });
            const sprite = b._sprite;
            const sw = sprite.width / s.dpr;
            const sh = sprite.height / s.dpr;
            const x = base.x - sw / 2;
            const y = base.y - sh / 2;

            sl.globalAlpha = s.alpha * (b.opacity ?? 1);
            sl.drawImage(
                sprite,
                Math.round(x * s.dpr), Math.round(y * s.dpr),
                Math.round(sw * s.dpr), Math.round(sh * s.dpr)
            );
        }
        this._needRebuildStatic = false;
    }

    private _loop(ts: number): void {
        if (!this.state.running) return;

        // FPS cap
        if (this.state.fpsCap > 0) {
            const interval = 1000 / this.state.fpsCap;
            if (ts - this._lastFrameTs < interval) {
                this._raf = requestAnimationFrame((t) => this._loop(t));
                return;
            }
        }

        const dt = Math.max(0, ts - this._lastFrameTs);
        this._lastFrameTs = ts;

        this._step(ts, dt);
        this._raf = requestAnimationFrame((t) => this._loop(t));
    }

    private _fullRedraw(ts: number): void {
        const s = this.state;
        if (this._needRebuildStatic) this._buildStaticLayer();
        const c = this.ctx;
        if (!c) return;

        // draw static base in device pixels
        c.save();
        c.setTransform(1, 0, 0, 1, 0, 0);
        c.clearRect(0, 0, this.canvas.width, this.canvas.height);
        c.drawImage(this._staticLayer, 0, 0);
        c.restore();

        // dynamic
        c.save();
        c.scale(s.dpr, s.dpr);
        c.globalCompositeOperation = s.composite;
        this._drawDynamics(ts, null);
        c.restore();
    }

    private _step(ts: number, dtMs: number): void {
        const s = this.state;
        if (!this.ctx) return;
        const tSec = ts / 1000;

        // smooth pointer
        s.px = lerp(s.px, s.tx, 0.1);
        s.py = lerp(s.py, s.ty, 0.1);

        if (this._needRebuildStatic) this._buildStaticLayer();

        const dirty: Rect[] = [];
        const pad = Math.max(s.damagePaddingPx, (s.blurPx * 1.5));
        const parallaxPx = s.parallaxVmax * (s.vMax / 100);

        for (const b of this._blobs.values()) {
            if (b._static || !b._sprite) continue;

            // advance easing using real delta-time
            if (b._ease) {
                const inc = clamp(dtMs / b._ease.durMs, 0, 1);
                b._ease.t = clamp(b._ease.t + inc, 0, 1);
                if (b._ease.t >= 1) { b._ease = null; this._classifyBlobStatic(b); this._needRebuildStatic = this._needRebuildStatic || b._static; }
            }

            const nowRect = this._blobRect(b, tSec, parallaxPx);
            const infNow = inflateRect(nowRect, pad);
            if (b._lastRect) dirty.push(inflateRect(b._lastRect, pad));
            dirty.push(infNow);
            b._nextRect = nowRect;
        }

        if (dirty.length === 0) {
            if (this._needRebuildStatic) this._fullRedraw(ts);
            return;
        }

        const merged = mergeRects(dirty, 2);
        const totalArea = s.w * s.h;
        const cover = merged.reduce((a, r) => a + r.w * r.h, 0) / totalArea;

        if (merged.length > s.maxDirtyRects || cover > s.fullRedrawThreshold) {
            this._fullRedraw(ts);
        } else {
            this._redrawDirty(ts, merged);
        }

        for (const b of this._blobs.values()) {
            if (b._static || !b._sprite) continue;
            b._lastRect = b._nextRect || null;
            b._nextRect = null;
        }
    }

    private _redrawDirty(ts: number, rects: Rect[]): void {
        const s = this.state;
        const c = this.ctx;
        if (!c) return;

        // 1) repair dirty regions from static layer (CSS px space)
        c.save();
        c.scale(s.dpr, s.dpr);
        const prevAlpha = c.globalAlpha;
        const prevComp = c.globalCompositeOperation;
        c.globalAlpha = 1;
        c.globalCompositeOperation = "source-over";
        for (const r of rects) {
            const sx = Math.max(0, Math.floor(r.x * s.dpr));
            const sy = Math.max(0, Math.floor(r.y * s.dpr));
            const sw = Math.ceil(r.w * s.dpr);
            const sh = Math.ceil(r.h * s.dpr);
            c.drawImage(this._staticLayer, sx, sy, sw, sh, r.x, r.y, r.w, r.h);
        }
        c.globalAlpha = prevAlpha;
        c.globalCompositeOperation = prevComp;
        c.restore();

        // 2) draw dynamics only if intersecting any dirty rect
        c.save();
        c.scale(s.dpr, s.dpr);
        c.globalCompositeOperation = s.composite;
        this._drawDynamics(ts, rects);
        c.restore();
    }

    private _drawDynamics(ts: number, limitRects: Rect[] | null): void {
        const s = this.state;
        const tSec = ts / 1000;
        const parallaxPx = s.parallaxVmax * (s.vMax / 100);
        const c = this.ctx!;
        for (const b of this._blobs.values()) {
            if (b._static || !b._sprite) continue;

            const st = this._blobStateNow(b, tSec, parallaxPx);
            const sprite = b._sprite!;
            const sw = (sprite.width / s.dpr) * st.scaleMul;
            const sh = (sprite.height / s.dpr) * st.scaleMul;
            const x = st.cx - sw / 2;
            const y = st.cy - sh / 2;
            const rect = { x, y, w: sw, h: sh };

            if (limitRects && !limitRects.some((r) => intersect(r, rect))) continue;

            c.globalAlpha = s.alpha * (b.opacity ?? 1) * st.alphaMul;
            c.drawImage(sprite, x, y, sw, sh);
        }
    }

    private _blobRect(b: BlobInternal, tSec: number, parallaxPx: number): Rect {
        const s = this.state;
        const st = this._blobStateNow(b, tSec, parallaxPx);
        const sprite = b._sprite!;
        const sw = (sprite.width / s.dpr) * st.scaleMul;
        const sh = (sprite.height / s.dpr) * st.scaleMul;
        return { x: st.cx - sw / 2, y: st.cy - sh / 2, w: sw, h: sh };
    }

    private _blobStateNow(b: BlobInternal, tSec: number, parallaxPx: number): { cx: number; cy: number; scaleMul: number; alphaMul: number } {
        const s = this.state;
        const base = resolveCenter(b.center, { w: s.w, h: s.h, vMax: s.vMax });

        // easing
        let cx = base.x, cy = base.y;
        if (b._ease) {
            const e = b._ease.ease(b._ease.t);
            cx = lerp(b._ease.from.x, b._ease.to.x, e);
            cy = lerp(b._ease.from.y, b._ease.to.y, e);
        }

        // parallax + drift
        const dx = s.px * parallaxPx * (b.parallaxScale ?? 1);
        const dy = s.py * parallaxPx * (b.parallaxScale ?? 1);

        const d = b.drift || { ax: 0, ay: 0, sx: 0, sy: 0, speed: 0 };
        const driftX = Math.sin(tSec * TAU * d.speed + 1.3) * (d.ax || 0)
            + Math.sin(tSec * TAU * (d.speed * 0.5) + 0.7) * (d.sx || 0);
        const driftY = Math.cos(tSec * TAU * d.speed + 0.9) * (d.ay || 0)
            + Math.cos(tSec * TAU * (d.speed * 0.6) + 0.4) * (d.sy || 0);

        // breath (scale/alpha)
        let scaleMul = 1;
        let alphaMul = 1;
        if (b.breath) {
            const speed = b.breath.speed ?? 0;
            const phase = b.breath.phase ?? 0;
            const p = Math.sin(TAU * (speed * tSec + phase));
            if (b.breath.scale) scaleMul = lerp(b.breath.scale[0], b.breath.scale[1], (p + 1) / 2);
            if (b.breath.opacity) alphaMul = lerp(b.breath.opacity[0], b.breath.opacity[1], (p + 1) / 2);
        }

        return { cx: cx + dx + driftX, cy: cy + dy + driftY, scaleMul, alphaMul };
    }
}
