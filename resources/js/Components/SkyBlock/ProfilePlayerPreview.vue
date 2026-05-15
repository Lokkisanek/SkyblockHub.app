<script setup>
import { inject, ref } from 'vue';
import PlayerModel from '@/Components/SkyBlock/PlayerModel.vue';
import { getHeadUrl } from '@/utils/textures';

const props = defineProps({
    uuid: { type: String, default: null },
    width: { type: Number, default: 208 },
    height: { type: Number, default: 400 },
});

const performanceMode = inject('profilePerformanceMode', ref(false));

const HEAD_SIZE = 64;
</script>

<template>
    <PlayerModel
        v-if="!performanceMode && uuid"
        :uuid="uuid"
        :width="width"
        :height="height"
    />
    <div
        v-else-if="uuid"
        class="profile-player-static"
        :style="{ width: `${width}px`, height: `${height}px` }"
    >
        <img
            :src="getHeadUrl(uuid, HEAD_SIZE)"
            alt=""
            class="profile-player-static__img"
            width="64"
            height="64"
            loading="lazy"
            decoding="async"
            draggable="false"
        />
    </div>
</template>
