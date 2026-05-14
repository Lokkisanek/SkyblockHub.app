<script setup>
/**
 * InventoryGrid — renders an MC-style inventory grid.
 *
 * Props:
 *   items      — flat array of items (nulls = empty slots)
 *   showHotbar — if true, reorder to show main inventory (9-35) then hotbar (0-8) when not using fixed rows
 *   columns    — column count (default 9)
 *   rows       — if set with columns, renders exactly columns×rows cells (padded with nulls). Hotbar order is merged into one block when showHotbar.
 */
import { computed } from 'vue';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    showHotbar: { type: Boolean, default: false },
    columns: { type: Number, default: 9 },
    rows: { type: Number, default: null },
});

const fixedSlotCount = computed(() => {
    if (props.rows == null || props.rows < 1 || props.columns < 1) {
        return null;
    }

    return props.columns * props.rows;
});

/** Flat list for fixed rows×columns layout (Hypixel slot indices). */
const displayItemsFixed = computed(() => {
    const n = fixedSlotCount.value;
    if (n === null) {
        return null;
    }

    const items = props.items ?? [];
    let seq = [];

    if (props.showHotbar && items.length >= 36) {
        for (let s = 9; s <= 35; s++) {
            seq.push(items[s] ?? null);
        }

        for (let s = 0; s <= 8; s++) {
            seq.push(items[s] ?? null);
        }
    } else {
        seq = [...items];
    }

    while (seq.length < n) {
        seq.push(null);
    }

    return seq.slice(0, n);
});

const gridColumnStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.columns}, calc(var(--inv-w) * ${(1 / props.columns).toFixed(4)}))`,
}));
</script>

<template>
    <div
        v-if="displayItemsFixed"
        class="inventory-grid"
        :style="gridColumnStyle"
    >
        <ItemSlot v-for="(item, idx) in displayItemsFixed" :key="'fx-' + idx" :item="item" />
    </div>
    <div
        v-else
        class="inventory-grid"
        :style="gridColumnStyle"
    >
        <template v-if="showHotbar && items.length >= 36">
            <template v-for="i in 27" :key="'main-' + i">
                <ItemSlot :item="items[i + 8]" />
            </template>

            <hr />

            <template v-for="i in 9" :key="'hot-' + i">
                <ItemSlot :item="items[i - 1]" />
            </template>
        </template>

        <template v-else>
            <ItemSlot v-for="(item, idx) in items" :key="idx" :item="item" />
        </template>
    </div>
</template>
