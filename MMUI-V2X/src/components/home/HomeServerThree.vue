<template>
  <div ref="shellRef" class="home-server-three" aria-hidden="true">
    <canvas ref="canvasRef" class="home-server-three__canvas"></canvas>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const props = defineProps({
  dark: {
    type: Boolean,
    default: false,
  },
});

const shellRef = ref(null);
const canvasRef = ref(null);

let renderer;
let scene;
let camera;
let model;
let environment;
let contactShadow;
let frameId = 0;
let resizeObserver;
let disposed = false;
const modelUrl = '/models/home-server.glb';

function frameModel(object) {
  const box = new THREE.Box3().setFromObject(object);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxAxis = Math.max(size.x, size.y, size.z) || 1;

  object.position.sub(center);
  object.scale.setScalar(3.86 / maxAxis);
  object.rotation.set(0, 0, 0);
  object.position.set(-0.12, -0.52, -0.04);
}

function createShadowTexture() {
  const size = 256;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const context = canvas.getContext('2d');
  const gradient = context.createRadialGradient(size / 2, size / 2, 8, size / 2, size / 2, size / 2);
  gradient.addColorStop(0, 'rgba(31, 74, 150, 0.13)');
  gradient.addColorStop(0.46, 'rgba(58, 113, 196, 0.065)');
  gradient.addColorStop(1, 'rgba(58, 113, 196, 0)');
  context.fillStyle = gradient;
  context.fillRect(0, 0, size, size);

  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  return texture;
}

function addContactShadow() {
  const material = new THREE.MeshBasicMaterial({
    map: createShadowTexture(),
    transparent: true,
    depthWrite: false,
  });
  contactShadow = new THREE.Mesh(new THREE.PlaneGeometry(3.15, 2.15), material);
  contactShadow.rotation.x = -Math.PI / 2;
  contactShadow.position.set(-0.04, -1.9, 0.04);
  contactShadow.renderOrder = -1;
  scene.add(contactShadow);
}

function createPremiumMaterial(source) {
  const name = source.name?.toLowerCase() || '';
  const common = {
    name: source.name,
    envMapIntensity: 0.72,
  };

  if (name.includes('porcelain')) {
    return new THREE.MeshPhysicalMaterial({
      ...common,
      color: '#d8e7f8',
      roughness: 0.32,
      metalness: 0,
      clearcoat: 0.72,
      clearcoatRoughness: 0.22,
      sheen: 0.18,
      sheenColor: '#ffffff',
      emissive: '#102c62',
      emissiveIntensity: 0.0015,
    });
  }

  if (name.includes('frost')) {
    return new THREE.MeshPhysicalMaterial({
      ...common,
      color: '#a9c1dc',
      roughness: 0.4,
      metalness: 0,
      clearcoat: 0.42,
      clearcoatRoughness: 0.3,
      emissive: '#183c76',
      emissiveIntensity: 0.0015,
    });
  }

  if (name.includes('enterprise azure')) {
    return new THREE.MeshPhysicalMaterial({
      ...common,
      color: '#0d2e94',
      roughness: 0.16,
      metalness: 0.04,
      clearcoat: 0.8,
      clearcoatRoughness: 0.12,
      emissive: '#03164d',
      emissiveIntensity: 0.004,
    });
  }

  if (name.includes('base ice')) {
    return new THREE.MeshPhysicalMaterial({
      ...common,
      color: '#86a4c6',
      roughness: 0.34,
      metalness: 0.01,
      clearcoat: 0.42,
      clearcoatRoughness: 0.26,
      emissive: '#244875',
      emissiveIntensity: 0.0015,
    });
  }

  if (name.includes('glass')) {
    const isTile = name.includes('tile');
    return new THREE.MeshPhysicalMaterial({
      ...common,
      color: isTile ? '#183cb7' : '#35bfe8',
      roughness: isTile ? 0.035 : 0.02,
      metalness: 0,
      transmission: isTile ? 0.48 : 0.82,
      thickness: isTile ? 0.18 : 0.4,
      ior: 1.16,
      transparent: true,
      opacity: isTile ? 0.78 : 0.62,
      clearcoat: 0.88,
      clearcoatRoughness: 0.06,
      emissive: isTile ? '#06218c' : '#007fb1',
      emissiveIntensity: isTile ? 0.045 : 0.08,
      depthWrite: false,
    });
  }

  if (name.includes('led')) {
    return new THREE.MeshStandardMaterial({
      ...common,
      color: name.includes('orange') ? '#ff8c4d' : '#ffd65a',
      roughness: 0.25,
      emissive: name.includes('orange') ? '#ff5b20' : '#ffc533',
      emissiveIntensity: 0.85,
    });
  }

  return source;
}

function normalizeMaterials(object) {
  object.traverse((child) => {
    if (!child.isMesh) return;

    child.castShadow = true;
    child.receiveShadow = false;

    const sourceMaterials = Array.isArray(child.material) ? child.material : [child.material];
    const materials = sourceMaterials.map((material) => createPremiumMaterial(material));
    child.material = Array.isArray(child.material) ? materials : materials[0];

    materials.forEach((material) => {
      if (!material) return;
      const name = material.name?.toLowerCase() || '';

      if (name.includes('porcelain') || name.includes('frost')) {
        material.roughness = name.includes('porcelain') ? 0.38 : 0.48;
        material.metalness = 0;
        material.color?.set(name.includes('porcelain') ? '#d8e7f8' : '#a9c1dc');
        material.emissive?.set('#1f3d78');
        material.emissiveIntensity = 0.0015;
        if ('clearcoat' in material) material.clearcoat = name.includes('porcelain') ? 0.58 : 0.36;
        if ('clearcoatRoughness' in material) material.clearcoatRoughness = 0.24;
      }

      if (name.includes('enterprise azure')) {
        material.color?.set('#0d2e94');
        material.roughness = 0.16;
        material.metalness = 0.04;
        material.emissive?.set('#03164d');
        material.emissiveIntensity = 0.004;
        if ('clearcoat' in material) material.clearcoat = 0.62;
        if ('clearcoatRoughness' in material) material.clearcoatRoughness = 0.12;
      }

      if (name.includes('base ice')) {
        material.color?.set('#86a4c6');
        material.roughness = 0.34;
        material.emissive?.set('#27436f');
        material.emissiveIntensity = 0.0015;
        if ('clearcoat' in material) material.clearcoat = 0.36;
      }

      if (name.includes('glass')) {
        material.transparent = true;
        material.depthWrite = false;
        material.opacity = name.includes('tile') ? 0.78 : 0.62;
        material.roughness = name.includes('tile') ? 0.035 : 0.018;
        material.metalness = 0;
        material.color?.set(name.includes('tile') ? '#183cb7' : '#35bfe8');
        material.emissive?.set(name.includes('tile') ? '#06218c' : '#007fb1');
        material.emissiveIntensity = name.includes('tile') ? 0.045 : 0.08;
        if ('transmission' in material) material.transmission = name.includes('tile') ? 0.45 : 0.86;
        if ('ior' in material) material.ior = 1.16;
        if ('thickness' in material) material.thickness = 0.36;
        if ('clearcoat' in material) material.clearcoat = 0.55;
        if ('clearcoatRoughness' in material) material.clearcoatRoughness = 0.08;
      }

      material.needsUpdate = true;
      if (material.map) material.map.colorSpace = THREE.SRGBColorSpace;
    });
  });
}

function updateTheme() {
  if (!scene) return;
  scene.background = null;
}

function addLights() {
  scene.add(new THREE.HemisphereLight('#ffffff', props.dark ? '#26344f' : '#9fb9df', 0.44));

  const key = new THREE.DirectionalLight('#ffffff', 0.68);
  key.position.set(-4.8, 7.8, 6.2);
  key.castShadow = true;
  key.shadow.mapSize.set(1024, 1024);
  scene.add(key);

  const fill = new THREE.DirectionalLight('#c1d4f4', 0.24);
  fill.position.set(4.6, 3.2, 5.8);
  scene.add(fill);

  const topSoft = new THREE.PointLight('#ffffff', props.dark ? 5 : 0.24, 9);
  topSoft.position.set(0.5, 4.2, 2.8);
  scene.add(topSoft);

  const blueRim = new THREE.PointLight('#4f82e8', props.dark ? 7 : 1.0, 7);
  blueRim.position.set(2.2, 0.2, 2.9);
  scene.add(blueRim);

  const cyanCore = new THREE.PointLight('#54d4ff', props.dark ? 6 : 0.58, 4.8);
  cyanCore.position.set(-0.18, -0.06, 1.05);
  scene.add(cyanCore);
}

function createRenderer() {
  renderer = new THREE.WebGLRenderer({
    canvas: canvasRef.value,
    antialias: true,
    alpha: true,
    preserveDrawingBuffer: true,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFShadowMap;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.NeutralToneMapping;
  renderer.toneMappingExposure = 0.58;
  renderer.setClearColor(0x000000, 0);
}

function buildScene() {
  scene = new THREE.Scene();
  updateTheme();

  camera = new THREE.OrthographicCamera(-3.4, 3.4, 2.72, -2.72, 0.1, 100);
  camera.position.set(4.35, 3.08, 5.55);
  camera.lookAt(-0.14, -0.28, 0.04);

  createRenderer();
  const pmrem = new THREE.PMREMGenerator(renderer);
  environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environment = environment;
  pmrem.dispose();
  addLights();
  addContactShadow();

  const loader = new GLTFLoader();
  loader.load(modelUrl, (gltf) => {
    if (disposed) return;

    model = gltf.scene;
    normalizeMaterials(model);
    frameModel(model);
    scene.add(model);
  });
}

function resizeRenderer() {
  if (!renderer || !shellRef.value || !camera) return;

  const { width, height } = shellRef.value.getBoundingClientRect();
  renderer.setSize(Math.max(1, width), Math.max(1, height), false);
  camera.left = -3.4;
  camera.right = 3.4;
  camera.top = 2.72;
  camera.bottom = -2.72;
  camera.updateProjectionMatrix();
}

function render() {
  frameId = requestAnimationFrame(render);
  if (model) {
    const time = performance.now() * 0.001;
    model.position.y = -0.52 + Math.sin(time * 0.7) * 0.008;
  }
  renderer.render(scene, camera);
}

function disposeObject(object) {
  object.traverse((child) => {
    if (child.geometry) child.geometry.dispose();
    if (child.material) {
      const materials = Array.isArray(child.material) ? child.material : [child.material];
      materials.forEach((material) => material.dispose());
    }
  });
}

onMounted(() => {
  buildScene();
  resizeObserver = new ResizeObserver(resizeRenderer);
  resizeObserver.observe(shellRef.value);
  resizeRenderer();
  render();
});

onBeforeUnmount(() => {
  disposed = true;
  cancelAnimationFrame(frameId);
  resizeObserver?.disconnect();
  if (model) disposeObject(model);
  if (contactShadow) disposeObject(contactShadow);
  environment?.dispose();
  renderer?.dispose();
});

watch(() => props.dark, updateTheme);
</script>

<style scoped>
.home-server-three {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.home-server-three__canvas {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
