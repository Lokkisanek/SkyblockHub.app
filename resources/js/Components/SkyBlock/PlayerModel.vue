<script setup>
/**
 * PlayerModel — Interactive 3D Minecraft player model (SkyCrypt-style).
 * Uses skinview3d (Three.js-based) with mouse/touch drag to rotate.
 *
 * Props:
 *   uuid  — Minecraft player UUID (used to fetch skin texture)
 *   skinName — Optional username/skin name fallback when UUID is unavailable
 *   width / height — canvas dimensions
 */
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    uuid:   { type: String, default: null },
    skinName: { type: String, default: null },
    width:  { type: Number, default: 200 },
    height: { type: Number, default: 400 },
});

const canvasRef = ref(null);
let viewer = null;

/**
 * Get the raw skin texture URL from Mojang via proxy (to avoid CORS).
 * We use mc-heads.net/skin/ which returns the raw skin PNG.
 */
function getSkinTextureUrl() {
    if (props.uuid) {
        return `https://mc-heads.net/skin/${props.uuid}`;
    }

    if (props.skinName) {
        return `https://mc-heads.net/skin/${encodeURIComponent(props.skinName)}`;
    }

    return null;
}

async function initViewer() {
    const skinUrl = getSkinTextureUrl();

    if (!canvasRef.value || !skinUrl) return;

    // Dynamically import to keep bundle lighter (three.js is heavy)
    const { SkinViewer, IdleAnimation } = await import('skinview3d');

    // Destroy previous viewer if exists
    if (viewer) {
        viewer.dispose();
        viewer = null;
    }

    viewer = new SkinViewer({
        canvas: canvasRef.value,
        width: props.width,
        height: props.height,
        skin: skinUrl,
        zoom: 0.9,
        fov: 50,
        animation: new IdleAnimation(),
        background: null,
    });

    // Animation speed
    viewer.animation.speed = 0.6;

    // Lighting — bright enough to see details clearly
    viewer.globalLight.intensity = 3.0;
    viewer.cameraLight.intensity = 1.0;

    // Controls: rotate yes, zoom no, pan no
    viewer.controls.enableRotate = true;
    viewer.controls.enableZoom = false;
    viewer.controls.enablePan = false;

    // Auto-rotate very slowly when not interacting
    viewer.autoRotate = false;
    viewer.autoRotateSpeed = 0;
}

onMounted(() => {
    nextTick(() => initViewer());
});

watch(() => props.uuid, () => {
    const skinUrl = getSkinTextureUrl();

    if (viewer && skinUrl) {
        viewer.loadSkin(skinUrl);
    } else {
        nextTick(() => initViewer());
    }
});

watch(() => props.skinName, () => {
    const skinUrl = getSkinTextureUrl();

    if (viewer && skinUrl) {
        viewer.loadSkin(skinUrl);
    } else {
        nextTick(() => initViewer());
    }
});

onBeforeUnmount(() => {
    if (viewer) {
        viewer.dispose();
        viewer = null;
    }
});
</script>

<template>
    <div class="player-model-container"
         :style="{ width: width + 'px', height: height + 'px' }">
        <canvas ref="canvasRef"
                class="player-model-canvas"
                :width="width"
                :height="height" />
        <div v-if="!uuid && !skinName" class="player-model-placeholder">
            <span>No player</span>
        </div>
    </div>
</template>

<style scoped>
.player-model-container {
    position: relative;
    cursor: grab;
    overflow: hidden;
}
.player-model-container:active {
    cursor: grabbing;
}
.player-model-canvas {
    display: block;
    width: 100%;
    height: 100%;
}
.player-model-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.3);
    font-size: 12px;
}
</style>
