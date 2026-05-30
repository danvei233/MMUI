<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue'
import LoginCard from '@/components/LoginCard.vue'
import { FluidBackground } from '@/assets/fluid-bg.ts'

type RGB = [number, number, number]

const canvasRef = ref<HTMLCanvasElement|null>(null)
let fb: any = null


function hex2rgb(hex: string): RGB {
  const h = hex.replace('#', '')
  return [
    parseInt(h.slice(0, 2), 16),
    parseInt(h.slice(2, 4), 16),
    parseInt(h.slice(4, 6), 16),
  ] as RGB
}


const CONFIG ={
  "global": { "alpha": 0.55, "blurPx": 32, "parallaxVmax": 2.2, "fpsCap": 45, "composite": "source-over", "livePreview": true },
  "ui": { "positionUnit": "percent", "sizeUnit": "vmax", "frameBg": "#f5f7fb", "canvasBg": "#ffffff", "showCanvasBorder": false },
  "blobs": [
    { "id": "b1", "diameter": "68vmax", "center": { "x": "18%", "y": "74%" }, "opacity": 0.97, "parallaxScale": 1.35,
      "layers": [{ "hex": "#6d6afc", "centerAlpha": 0.56, "midAlpha": 0.22, "edgeAlpha": 0 }, { "hex": "#b9a9ff", "centerAlpha": 0.44, "midAlpha": 0.17, "edgeAlpha": 0 }],
      "drift": { "ax": 10, "ay": 8, "sx": 10, "sy": 8, "speed": 0.038 },
      "breath": { "enable": true, "scaleMin": 0.94, "scaleMax": 1.065, "opacityMin": 0.8, "opacityMax": 1.0, "speed": 0.032, "phase": 0.06 } },
    { "id": "b2", "diameter": "74vmax", "center": { "x": "86%", "y": "20%" }, "opacity": 0.97, "parallaxScale": 1.32,
      "layers": [{ "hex": "#b9a9ff", "centerAlpha": 0.52, "midAlpha": 0.21, "edgeAlpha": 0 }, { "hex": "#7fd8ff", "centerAlpha": 0.4, "midAlpha": 0.15, "edgeAlpha": 0 }],
      "drift": { "ax": 12, "ay": 10, "sx": -12, "sy": 9, "speed": 0.037 },
      "breath": { "enable": true, "scaleMin": 0.942, "scaleMax": 1.062, "opacityMin": 0.8, "opacityMax": 1.0, "speed": 0.031, "phase": 0.4 } },
    { "id": "b3", "diameter": "46vmax", "center": { "x": "34%", "y": "30%" }, "opacity": 0.94, "parallaxScale": 1.28,
      "layers": [{ "hex": "#6d6afc", "centerAlpha": 0.46, "midAlpha": 0.17, "edgeAlpha": 0 }, { "hex": "#ffffff", "centerAlpha": 0.07, "midAlpha": 0.025, "edgeAlpha": 0 }],
      "drift": { "ax": -10, "ay": 8, "sx": 10, "sy": -8, "speed": 0.036 },
      "breath": { "enable": true, "scaleMin": 0.946, "scaleMax": 1.058, "opacityMin": 0.86, "opacityMax": 1.0, "speed": 0.03, "phase": 0.22 } },
    { "id": "b4", "diameter": "50.06vmax", "center": { "x": "68%", "y": "68%" }, "opacity": 0.95, "parallaxScale": 1.4,
      "layers": [{ "hex": "#7fd8ff", "centerAlpha": 0.38, "midAlpha": 0.14, "edgeAlpha": 0 }, { "hex": "#6d6afc", "centerAlpha": 0.32, "midAlpha": 0.12, "edgeAlpha": 0 }],
      "drift": { "ax": -12, "ay": 12, "sx": 9, "sy": -11, "speed": 0.0365 },
      "breath": { "enable": true, "scaleMin": 0.944, "scaleMax": 1.06, "opacityMin": 0.84, "opacityMax": 1.0, "speed": 0.0295, "phase": 0.55 } }
  ]
}


function addFromConfig() {
  // init
  fb = new FluidBackground(canvasRef.value!, {
    pointerTracking: true,
    alpha: CONFIG.global.alpha,
    blurPx: CONFIG.global.blurPx,
    parallaxVmax: CONFIG.global.parallaxVmax,
    fpsCap: CONFIG.global.fpsCap,
  })

  // blobs
  for (const b of CONFIG.blobs) {
    const layers = (b.layers || []).map((L: any) => ({
      color: hex2rgb(L.hex),
      centerAlpha: L.centerAlpha ?? 0.5,
      midAlpha: L.midAlpha ?? 0.2,
      edgeAlpha: L.edgeAlpha ?? 0,
    }))

    const breath = b.breath
        ? {
          enable: b.breath.enable ?? true,
          scaleMin: b.breath.scaleMin, scaleMax: b.breath.scaleMax,
          opacityMin: b.breath.opacityMin, opacityMax: b.breath.opacityMax,
          speed: b.breath.speed, phase: b.breath.phase,
          // 旧版 fluid-bg 兼容
          scale: [b.breath.scaleMin, b.breath.scaleMax],
          opacity: [b.breath.opacityMin, b.breath.opacityMax],
        }
        : undefined

    fb.addBlob({
      id: b.id,
      diameter: b.diameter,
      center: b.center,                // 支持百分比/单位字符串
      opacity: b.opacity,
      parallaxScale: b.parallaxScale,
      layers,
      drift: b.drift,
      breath,
    })
  }

  fb.start?.()  // 若你的实现需要显式 start，这行生效；否则忽略
}

onMounted(() => {
  addFromConfig()
})

onBeforeUnmount(() => {
  fb?.destroy?.()
  fb = null
})
</script>

<template>
  <div class="page">
    <canvas ref="canvasRef" class="fluid-canvas" aria-hidden="true"></canvas>
    <div class="content"><LoginCard/></div>
  </div>
</template>

<style scoped>
.page{
  min-height:100dvh;
  background:#ffffff; /* CONFIG.ui.canvasBg */
  position:relative;
  overflow:hidden;
}
.content{
  position:fixed;
  top:50%; left:50%;
  transform:translate(-50%,-50%);
  z-index:1;
}
.fluid-canvas{
  position:fixed; inset:0; z-index:0; pointer-events:none;
  /* outline: 1px dashed #e5e7eb; */
}
</style>
