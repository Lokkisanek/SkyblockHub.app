<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlayerModel from '@/Components/SkyBlock/PlayerModel.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    canEditDashboard: { type: Boolean, default: false },
    requiresLogin: { type: Boolean, default: true },
    requiresMinecraftLink: { type: Boolean, default: false },
    dashboard: { type: Object, default: null },
    widgetTemplates: { type: Array, default: () => [] },
    dashboardLimits: { type: Object, default: () => ({ free_slots: 1, total_slots: 3, unlocked_slots: 1, locked_slots: [2, 3] }) },
    subscriptionFeatures: { type: Object, default: () => ({ priority_widget_updates: false }) },
    activeSlotIndex: { type: Number, default: 1 },
    liveWidgetData: { type: Object, default: () => ({ items: {}, event: null }) },
});

const gridRef = ref(null);
const gridBounds = ref({ width: 0, height: 0 });
const editMode = ref(false);
const showTemplateModal = ref(false);
const isSaving = ref(false);
const isDirty = ref(false);
const feedback = ref('');
const isPublic = ref(Boolean(props.dashboard?.is_public ?? false));
const profilePayloadByUsername = ref({});
const profileLoadingByUsername = ref({});
const interaction = ref(null);
const selectedWidgetId = ref(null);
const dragGhost = ref(null);
const resizeObserver = ref(null);
const layoutUndoStack = ref([]);
const initialEditSnapshot = ref(null);

const gridColumns = computed(() => Number(props.dashboard?.grid_columns ?? 20));
const gridRows = computed(() => Number(props.dashboard?.grid_rows ?? 20));
const canvasAspectRatio = computed(() => `${gridColumns.value}/${gridRows.value}`);
const canOpenEdit = computed(() => props.canEditDashboard);

const templateMap = computed(() => {
    const map = {};
    for (const template of props.widgetTemplates) {
        map[template.type] = template;
    }
    return map;
});

const widgets = ref((props.dashboard?.widgets ?? []).map((widget) => normalizeWidget(widget)));

const slotCards = computed(() => {
    const total = Number(props.dashboardLimits?.total_slots ?? 3);
    const unlocked = Number(props.dashboardLimits?.unlocked_slots ?? 1);

    return Array.from({ length: total }, (_, index) => ({
        index: index + 1,
        isActive: index + 1 === props.activeSlotIndex,
        isLocked: index + 1 > unlocked,
    }));
});
const hasLockedSlots = computed(() => slotCards.value.some((slot) => slot.isLocked));
const showEditModeUpgradeCta = computed(() => !props.requiresLogin && (hasLockedSlots.value || !props.subscriptionFeatures?.priority_widget_updates));

const profileWidgetTypes = new Set(['skin_view_widget', 'inventory_gui_widget']);
const totalGridCells = computed(() => gridColumns.value * gridRows.value);
const selectedWidget = computed(() => widgets.value.find((widget) => widget.clientId === selectedWidgetId.value) ?? null);
const visibilityStatusLabel = computed(() => (isPublic.value ? t('dashboard.publicDashboard') : t('dashboard.privateDashboard')));

function cloneWidgetSnapshot(widget) {
    return {
        id: widget.id ?? null,
        clientId: widget.clientId,
        type: widget.type,
        title: widget.title,
        x: Number(widget.x),
        y: Number(widget.y),
        w: Number(widget.w),
        h: Number(widget.h),
        settings: JSON.parse(JSON.stringify(widget.settings ?? {})),
        pulse: false,
    };
}

function currentLayoutSnapshot() {
    return {
        isPublic: Boolean(isPublic.value),
        widgets: widgets.value.map((widget) => cloneWidgetSnapshot(widget)),
    };
}

function applyLayoutSnapshot(snapshot) {
    if (!snapshot) {
        return;
    }

    isPublic.value = Boolean(snapshot.isPublic);
    widgets.value = (snapshot.widgets ?? []).map((widget) => cloneWidgetSnapshot(widget));

    if (selectedWidgetId.value && !widgets.value.some((widget) => widget.clientId === selectedWidgetId.value)) {
        selectedWidgetId.value = null;
    }

    dragGhost.value = null;
    interaction.value = null;
}

function pushUndoSnapshot(snapshot = currentLayoutSnapshot()) {
    layoutUndoStack.value = [...layoutUndoStack.value, snapshot].slice(-40);
}

function undoLayoutChange() {
    if (!props.canEditDashboard || !editMode.value || layoutUndoStack.value.length === 0) {
        return;
    }

    const previous = layoutUndoStack.value[layoutUndoStack.value.length - 1];
    layoutUndoStack.value = layoutUndoStack.value.slice(0, -1);
    applyLayoutSnapshot(previous);
    markDirty();
    clearFeedbackSoon('Undid last layout change.');
}

function resetLayoutChanges() {
    if (!props.canEditDashboard || !editMode.value || !initialEditSnapshot.value) {
        return;
    }

    pushUndoSnapshot();
    applyLayoutSnapshot(initialEditSnapshot.value);
    isDirty.value = false;
    clearFeedbackSoon('Layout reset to edit start state.');
}

function templateForType(type) {
    return templateMap.value[type] ?? null;
}

function normalizeWidget(widget) {
    const template = templateForType(widget.type);
    const fixedWidth = Number(template?.default_size?.w ?? 2);
    const fixedHeight = Number(template?.default_size?.h ?? 2);

    return {
        id: widget.id ?? null,
        clientId: `widget-${widget.id ?? `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`}`,
        type: widget.type,
        title: widget.title || template?.default_title || 'Widget',
        x: Number(widget.x ?? 1),
        y: Number(widget.y ?? 1),
        w: fixedWidth,
        h: fixedHeight,
        settings: {
            ...(template?.default_settings ?? {}),
            ...(widget.settings ?? {}),
        },
        pulse: false,
    };
}

function measureCanvas() {
    if (!gridRef.value) {
        return;
    }

    const rect = gridRef.value.getBoundingClientRect();
    gridBounds.value = { width: rect.width, height: rect.height };
}

function vibrate(duration = 6) {
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        navigator.vibrate(duration);
    }
}

function markDirty() {
    isDirty.value = true;
    feedback.value = '';
}

function clearFeedbackSoon(message) {
    feedback.value = message;
    window.setTimeout(() => {
        if (feedback.value === message) {
            feedback.value = '';
        }
    }, 2200);
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function widgetStyle(widget) {
    const isDragging = interaction.value?.widgetId === widget.clientId;
    const dragOffsetX = Number(interaction.value?.offsetX ?? 0);
    const dragOffsetY = Number(interaction.value?.offsetY ?? 0);

    return {
        gridColumn: `${widget.x} / span ${widget.w}`,
        gridRow: `${widget.y} / span ${widget.h}`,
        transform: isDragging ? `translate3d(${dragOffsetX}px, ${dragOffsetY}px, 0)` : undefined,
        zIndex: isDragging ? 8 : 1,
    };
}

function hasCollision(candidate, exceptClientId = null) {
    return widgets.value.some((widget) => {
        if (widget.clientId === exceptClientId) {
            return false;
        }

        const overlapX = candidate.x < widget.x + widget.w && candidate.x + candidate.w > widget.x;
        const overlapY = candidate.y < widget.y + widget.h && candidate.y + candidate.h > widget.y;

        return overlapX && overlapY;
    });
}

function findFirstSpot(w, h) {
    for (let row = 1; row <= gridRows.value - h + 1; row++) {
        for (let col = 1; col <= gridColumns.value - w + 1; col++) {
            const candidate = { x: col, y: row, w, h };
            if (!hasCollision(candidate)) {
                return { x: col, y: row };
            }
        }
    }

    return null;
}

function findNearestOpenSpot(targetX, targetY, w, h, exceptClientId) {
    let best = null;
    let bestDistance = Number.POSITIVE_INFINITY;

    for (let row = 1; row <= gridRows.value - h + 1; row++) {
        for (let col = 1; col <= gridColumns.value - w + 1; col++) {
            const candidate = { x: col, y: row, w, h };
            if (hasCollision(candidate, exceptClientId)) {
                continue;
            }

            const distance = Math.abs(col - targetX) + Math.abs(row - targetY);
            if (distance < bestDistance) {
                best = candidate;
                bestDistance = distance;
            }
        }
    }

    return best;
}

function beginWidgetInteraction(mode, widget, event) {
    if (!props.canEditDashboard || !editMode.value || mode !== 'drag') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    measureCanvas();

    interaction.value = {
        mode,
        widgetId: widget.clientId,
        startX: event.clientX,
        startY: event.clientY,
        originX: widget.x,
        originY: widget.y,
        originW: widget.w,
        originH: widget.h,
        offsetX: 0,
        offsetY: 0,
        moved: false,
        beforeSnapshot: currentLayoutSnapshot(),
    };

    selectedWidgetId.value = widget.clientId;
    dragGhost.value = { x: widget.x, y: widget.y, w: widget.w, h: widget.h };

    vibrate(6);

    window.addEventListener('pointermove', onGlobalPointerMove, { passive: false });
    window.addEventListener('pointerup', onGlobalPointerUp, { once: true });
}

function onGlobalPointerMove(event) {
    if (!interaction.value || !gridBounds.value.width || !gridBounds.value.height) {
        return;
    }

    const widget = widgets.value.find((entry) => entry.clientId === interaction.value.widgetId);
    if (!widget) {
        return;
    }

    const cellWidth = gridBounds.value.width / gridColumns.value;
    const cellHeight = gridBounds.value.height / gridRows.value;
    const rawDeltaCols = (event.clientX - interaction.value.startX) / cellWidth;
    const rawDeltaRows = (event.clientY - interaction.value.startY) / cellHeight;
    const deltaCols = Math.round(rawDeltaCols);
    const deltaRows = Math.round(rawDeltaRows);

    if (interaction.value.mode === 'drag') {
        const nextX = clamp(interaction.value.originX + deltaCols, 1, gridColumns.value - widget.w + 1);
        const nextY = clamp(interaction.value.originY + deltaRows, 1, gridRows.value - widget.h + 1);
        let candidate = { x: nextX, y: nextY, w: widget.w, h: widget.h };

        if (hasCollision(candidate, widget.clientId)) {
            candidate = findNearestOpenSpot(nextX, nextY, widget.w, widget.h, widget.clientId);
        }

        interaction.value.offsetX = (rawDeltaCols - deltaCols) * cellWidth;
        interaction.value.offsetY = (rawDeltaRows - deltaRows) * cellHeight;

        if (candidate && (widget.x !== candidate.x || widget.y !== candidate.y)) {
            widget.x = candidate.x;
            widget.y = candidate.y;
            interaction.value.moved = true;
        }

        dragGhost.value = candidate ?? null;
    }

}

function onGlobalPointerUp() {
    if (!interaction.value) {
        return;
    }

    const widget = widgets.value.find((entry) => entry.clientId === interaction.value.widgetId);
    if (widget) {
        if (interaction.value.moved) {
            pushUndoSnapshot(interaction.value.beforeSnapshot);
            markDirty();
        }

        widget.pulse = true;
        window.setTimeout(() => {
            widget.pulse = false;
        }, 220);
    }

    interaction.value = null;
    dragGhost.value = null;
    window.removeEventListener('pointermove', onGlobalPointerMove);
    vibrate(4);
}

function selectWidget(clientId) {
    if (!editMode.value) {
        return;
    }

    selectedWidgetId.value = clientId;
}

function inventoryGridStyle(widget, showHotbar = false) {
    if (!gridBounds.value.width || !gridBounds.value.height) {
        return { '--inv-w': showHotbar ? '220px' : '180px' };
    }

    const rows = showHotbar ? 5 : 4;
    const cellWidth = gridBounds.value.width / gridColumns.value;
    const cellHeight = gridBounds.value.height / gridRows.value;
    const usableWidth = Math.max(140, cellWidth * widget.w - 24);
    const usableHeight = Math.max(92, cellHeight * widget.h - (editMode.value ? 66 : 24));
    const widthFromHeight = (usableHeight / rows) * 9;
    const target = Math.max(128, Math.min(usableWidth, widthFromHeight)) * 0.92;

    return { '--inv-w': `${Math.floor(target)}px` };
}

function skinModelSize(widget) {
    if (!gridBounds.value.width || !gridBounds.value.height) {
        return { width: 124, height: 206 };
    }

    const cellWidth = gridBounds.value.width / gridColumns.value;
    const cellHeight = gridBounds.value.height / gridRows.value;
    const maxWidth = Math.max(96, cellWidth * widget.w - 18);
    const maxHeight = Math.max(160, cellHeight * widget.h - 20);
    const width = Math.min(maxWidth, maxHeight * 0.58);
    const height = Math.min(maxHeight, width * 1.7);

    return {
        width: Math.floor(Math.max(96, width)),
        height: Math.floor(Math.max(160, height)),
    };
}

function toggleEditMode() {
    if (!canOpenEdit.value) {
        return;
    }

    editMode.value = !editMode.value;
    if (editMode.value) {
        initialEditSnapshot.value = currentLayoutSnapshot();
        layoutUndoStack.value = [];
    } else {
        showTemplateModal.value = false;
        selectedWidgetId.value = null;
        dragGhost.value = null;
        layoutUndoStack.value = [];
        initialEditSnapshot.value = null;
    }
}

function openAddWidgetsModal() {
    if (!props.canEditDashboard || !editMode.value) {
        return;
    }

    showTemplateModal.value = true;
}

function addWidget(template) {
    if (!props.canEditDashboard) {
        return;
    }

    const width = Number(template.default_size?.w ?? 2);
    const height = Number(template.default_size?.h ?? 2);
    const spot = findFirstSpot(width, height);

    if (!spot) {
        clearFeedbackSoon('No free space left in the dashboard canvas.');
        return;
    }

    pushUndoSnapshot();

    widgets.value.push({
        id: null,
        clientId: `widget-new-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        type: template.type,
        title: template.default_title,
        x: spot.x,
        y: spot.y,
        w: width,
        h: height,
        settings: { ...(template.default_settings ?? {}) },
        pulse: true,
    });

    vibrate(8);
    showTemplateModal.value = false;
    markDirty();

    window.setTimeout(() => {
        const created = widgets.value[widgets.value.length - 1];
        if (created) {
            created.pulse = false;
        }
    }, 220);
}

function removeWidget(clientId) {
    if (!props.canEditDashboard) {
        return;
    }

    pushUndoSnapshot();

    widgets.value = widgets.value.filter((widget) => widget.clientId !== clientId);
    markDirty();
    vibrate(4);
}

function saveDashboard() {
    if (!props.canEditDashboard || !editMode.value || isSaving.value) {
        return;
    }

    isSaving.value = true;

    router.post(route('dashboard.save'), {
        slot_index: props.activeSlotIndex,
        is_public: isPublic.value,
        widgets: widgets.value.map((widget) => {
            const template = templateForType(widget.type);
            const fixedWidth = Number(template?.default_size?.w ?? widget.w ?? 2);
            const fixedHeight = Number(template?.default_size?.h ?? widget.h ?? 2);

            return {
            id: widget.id,
            type: widget.type,
            title: widget.title,
            x: widget.x,
            y: widget.y,
            w: fixedWidth,
            h: fixedHeight,
            settings: widget.settings,
            };
        }),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isDirty.value = false;
            editMode.value = false;
            showTemplateModal.value = false;
            clearFeedbackSoon('Dashboard saved.');
            vibrate(10);
        },
        onError: () => {
            clearFeedbackSoon('Save failed. Check collisions, bounds, and entitlement.');
        },
        onFinish: () => {
            isSaving.value = false;
        },
    });
}

function profileKey(widget) {
    return (widget.settings?.username ?? '').trim().toLowerCase();
}

function profilePayload(widget) {
    const key = profileKey(widget);
    if (!key) {
        return null;
    }

    return profilePayloadByUsername.value[key] ?? null;
}

function buildProfilePayload(apiData) {
    const profiles = apiData?.profiles ?? {};
    const profileKeys = Object.keys(profiles);

    if (profileKeys.length === 0) {
        return null;
    }

    const selectedKey = profileKeys.find((key) => profiles[key]?.selected) ?? profileKeys[0];
    const currentData = profiles[selectedKey]?.data ?? null;

    if (!currentData) {
        return null;
    }

    const skills = currentData.skills ?? {};
    const skillEntries = Object.entries(skills).filter(([, entry]) => entry && typeof entry === 'object');
    const totalSkillLevel = skillEntries.reduce((sum, [, entry]) => sum + Number(entry.level ?? 0), 0);
    const topSkill = skillEntries
        .map(([name, entry]) => ({ name, level: Number(entry.level ?? 0) }))
        .sort((a, b) => b.level - a.level)[0] ?? null;

    const inventory = Array.isArray(currentData.inventory) ? currentData.inventory : [];
    const inventoryUsedSlots = inventory.filter((slot) => slot !== null).length;
    const armor = Array.isArray(currentData.armor) ? currentData.armor : [];
    const equipment = Array.isArray(currentData.equipment) ? currentData.equipment : [];

    return {
        uuid: apiData.uuid ?? null,
        displayname: apiData.displayname ?? 'Unknown',
        currentData,
        summary: {
            averageSkillLevel: skillEntries.length ? totalSkillLevel / skillEntries.length : 0,
            inventoryUsedSlots,
            armorCount: armor.filter(Boolean).length,
            equipmentCount: equipment.filter(Boolean).length,
            topSkill,
        },
    };
}

async function fetchProfilePayload(username) {
    const normalized = username.trim().toLowerCase();

    if (!normalized || profileLoadingByUsername.value[normalized]) {
        return;
    }

    if (profilePayloadByUsername.value[normalized]) {
        return;
    }

    profileLoadingByUsername.value = {
        ...profileLoadingByUsername.value,
        [normalized]: true,
    };

    try {
        const response = await fetch(`/api/skycrypt/${encodeURIComponent(username)}`);
        const json = await response.json();

        if (!response.ok) {
            profilePayloadByUsername.value = {
                ...profilePayloadByUsername.value,
                [normalized]: { error: json?.error || 'Profile fetch failed' },
            };
            return;
        }

        profilePayloadByUsername.value = {
            ...profilePayloadByUsername.value,
            [normalized]: buildProfilePayload(json.data ?? {}) ?? { error: 'No profile data available' },
        };
    } catch {
        profilePayloadByUsername.value = {
            ...profilePayloadByUsername.value,
            [normalized]: { error: 'Network error while loading profile' },
        };
    } finally {
        profileLoadingByUsername.value = {
            ...profileLoadingByUsername.value,
            [normalized]: false,
        };
    }
}

function refreshLiveProfiles() {
    const usernames = Array.from(
        new Set(
            widgets.value
                .filter((widget) => profileWidgetTypes.has(widget.type))
                .map((widget) => (widget.settings?.username ?? '').trim())
                .filter(Boolean)
        )
    );

    usernames.forEach((username) => {
        fetchProfilePayload(username);
    });
}

function widgetProfileData(widget) {
    const payload = profilePayload(widget);
    return payload?.error ? null : payload;
}

function widgetStatusText(widget) {
    const key = profileKey(widget);
    const username = (widget.settings?.username ?? '').trim();

    if (!username) {
        return 'No profile data';
    }

    if (profileLoadingByUsername.value[key]) {
        return 'Loading';
    }

    const payload = profilePayload(widget);
    if (payload?.error) {
        if ((payload.error || '').toLowerCase().includes('no profile')) {
            return 'No profile data';
        }

        return 'Invalid username';
    }

    if (!widgetProfileData(widget)) {
        return 'No profile data';
    }

    return '';
}

function widgetStatusClass(widget) {
    const text = widgetStatusText(widget);

    if (text === 'Invalid username') {
        return 'widget-state-copy widget-state-copy--error';
    }

    return 'widget-state-copy';
}

const profileSignature = computed(() =>
    widgets.value
        .filter((widget) => profileWidgetTypes.has(widget.type))
        .map((widget) => `${widget.type}:${(widget.settings?.username ?? '').trim().toLowerCase()}`)
        .join('|')
);

watch(profileSignature, () => {
    refreshLiveProfiles();
}, { immediate: true });

onMounted(() => {
    measureCanvas();
    refreshLiveProfiles();

    if (gridRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver.value = new ResizeObserver(() => {
            measureCanvas();
        });

        resizeObserver.value.observe(gridRef.value);
    }

    window.addEventListener('resize', measureCanvas);
});

onBeforeUnmount(() => {
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }

    window.removeEventListener('resize', measureCanvas);
    window.removeEventListener('pointermove', onGlobalPointerMove);
    window.removeEventListener('pointerup', onGlobalPointerUp);
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-white/40">{{ t('dashboard.kicker') }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2.5">
                            <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ t('dashboard.heading') }}</h1>
                            <span class="inline-flex items-center rounded-full border border-amber-300/35 bg-amber-300/12 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-100">
                                {{ t('dashboard.betaTag') }}
                            </span>
                            <Link
                                :href="route('dashboard.info')"
                                class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-white/75 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
                            >
                                {{ t('dashboard.seeMoreInfo') }}
                            </Link>
                        </div>
                    </div>

                    <button
                        class="apple-control-button"
                        :disabled="!canOpenEdit"
                        @click="toggleEditMode"
                    >
                        {{ editMode ? t('dashboard.done') : t('dashboard.edit') }}
                    </button>
                </div>

                <p v-if="requiresLogin" class="mb-4 rounded-2xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                    {{ t('dashboard.loginRequired') }}
                    <a :href="route('auth.discord')" class="ml-1 font-semibold underline">{{ t('dashboard.loginDiscord') }}</a>
                </p>

                <p v-else-if="requiresMinecraftLink" class="mb-4 rounded-2xl border border-indigo-400/30 bg-indigo-400/10 px-4 py-3 text-sm text-indigo-100">
                    {{ t('dashboard.mcRequired') }}
                    <Link :href="route('profile.edit')" class="ml-1 font-semibold underline">{{ t('dashboard.openProfileSettings') }}</Link>
                </p>

                <div v-if="feedback" class="mb-4 inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-sm text-white/80">
                    {{ feedback }}
                </div>

                <div v-if="!requiresLogin" class="slot-strip">
                    <Link
                        v-for="slot in slotCards"
                        :key="slot.index"
                        :href="slot.isLocked ? '#' : route('dashboard', { slot: slot.index })"
                        @click="slot.isLocked && $event.preventDefault()"
                        class="slot-chip"
                        :class="{
                            'slot-chip--active': slot.isActive,
                            'slot-chip--locked': slot.isLocked,
                        }"
                        :aria-disabled="slot.isLocked"
                        :tabindex="slot.isLocked ? -1 : 0"
                    >
                        <span>{{ t('dashboard.slot', { n: slot.index }) }}</span>
                        <small>{{ slot.isLocked ? t('dashboard.locked') : (slot.isActive ? t('dashboard.active') : t('dashboard.open')) }}</small>
                    </Link>
                </div>

                <div class="dashboard-toolbar-row">
                    <div class="dashboard-toolbar-spacer"></div>
                    <div class="dashboard-toolbar-actions" v-if="editMode">
                        <Link
                            v-if="showEditModeUpgradeCta"
                            :href="route('billing')"
                            class="apple-secondary-button"
                        >
                            {{ t('dashboard.upgradeLink') }}
                        </Link>

                        <div v-if="selectedWidget" class="dashboard-toolbar-settings">
                            <label class="widget-field-label">{{ t('dashboard.widgetData') }}</label>

                            <template v-if="selectedWidget.type === 'skin_view_widget'">
                                <input v-model="selectedWidget.settings.username" class="widget-input widget-input--compact" placeholder="Minecraft username" @input="markDirty" />
                            </template>

                            <template v-else>
                                <input v-model="selectedWidget.settings.username" class="widget-input widget-input--compact" placeholder="Minecraft username" @input="markDirty" />
                                <label class="dashboard-toolbar-checks"><input v-model="selectedWidget.settings.show_hotbar" type="checkbox" @change="markDirty" /> {{ t('dashboard.showHotbar') }}</label>
                            </template>
                        </div>

                        <label class="dashboard-toggle-row dashboard-toggle-row--compact">
                            <span>{{ t('dashboard.public') }}</span>
                            <input v-model="isPublic" type="checkbox" @change="markDirty" />
                        </label>
                        <span class="visibility-chip" :class="{ 'visibility-chip--public': isPublic, 'visibility-chip--private': !isPublic }">
                            {{ visibilityStatusLabel }}
                        </span>
                        <button class="apple-secondary-button" :disabled="layoutUndoStack.length === 0" @click="undoLayoutChange">
                            {{ t('dashboard.undo') }}
                        </button>
                        <button class="apple-secondary-button" :disabled="!isDirty" @click="resetLayoutChanges">
                            {{ t('dashboard.reset') }}
                        </button>
                        <button class="apple-primary-button" @click="openAddWidgetsModal">{{ t('dashboard.addWidgets') }}</button>
                        <button class="apple-save-button" :disabled="!isDirty || isSaving" @click="saveDashboard">
                            {{ isSaving ? t('dashboard.saving') : t('dashboard.saveChanges') }}
                        </button>
                    </div>
                </div>

                <section>
                    <div
                        ref="gridRef"
                        class="dashboard-canvas"
                        :style="{
                            aspectRatio: canvasAspectRatio,
                            gridTemplateColumns: `repeat(${gridColumns}, minmax(0, 1fr))`,
                            gridTemplateRows: `repeat(${gridRows}, minmax(0, 1fr))`,
                        }"
                    >
                            <div v-if="editMode" class="dashboard-grid-overlay" :style="{ gridTemplateColumns: `repeat(${gridColumns}, minmax(0, 1fr))`, gridTemplateRows: `repeat(${gridRows}, minmax(0, 1fr))` }">
                                <span v-for="index in totalGridCells" :key="`grid-cell-${index}`"></span>
                            </div>

                            <div v-if="widgets.length === 0" class="dashboard-empty-state">
                                <p class="text-sm font-medium text-white/65">{{ t('dashboard.emptyTitle') }}</p>
                                <p class="mt-1 text-xs text-white/40">{{ t('dashboard.emptyHint') }}</p>
                            </div>

                            <article
                                v-for="widget in widgets"
                                :key="widget.clientId"
                                class="dashboard-widget"
                                :class="{
                                    'dashboard-widget--editing': editMode,
                                    'dashboard-widget--pulse': widget.pulse,
                                    'dashboard-widget--dragging': interaction?.widgetId === widget.clientId,
                                }"
                                :style="widgetStyle(widget)"
                                @pointerdown="selectWidget(widget.clientId)"
                            >
                                <header v-if="editMode" class="dashboard-widget__header">
                                    <div class="dashboard-widget__actions">
                                        <button class="widget-action widget-action--drag" type="button" @pointerdown="beginWidgetInteraction('drag', widget, $event)">↕</button>
                                        <button class="widget-action widget-action--remove" type="button" @click="removeWidget(widget.clientId)">×</button>
                                    </div>
                                </header>

                                <div class="dashboard-widget__content">
                                    <template v-if="widget.type === 'skin_view_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-skin-preview">
                                            <PlayerModel :uuid="widgetProfileData(widget).uuid" :width="skinModelSize(widget).width" :height="skinModelSize(widget).height" :zoom="0.72" />
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else>
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-inventory-panel">
                                            <InventoryGrid :items="widgetProfileData(widget).currentData.inventory ?? []" :showHotbar="Boolean(widget.settings.show_hotbar)" :style="inventoryGridStyle(widget, Boolean(widget.settings.show_hotbar))" />
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>
                                </div>

                            </article>

                            <div
                                v-if="editMode && dragGhost"
                                class="dashboard-drag-ghost"
                                :style="{
                                    gridColumn: `${dragGhost.x} / span ${dragGhost.w}`,
                                    gridRow: `${dragGhost.y} / span ${dragGhost.h}`,
                                }"
                            ></div>
                    </div>
                </section>
            </div>
        </div>

        <transition name="fade-scale">
            <div v-if="showTemplateModal" class="dashboard-modal-backdrop" @click.self="showTemplateModal = false">
                <div class="dashboard-modal">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/40">{{ t('dashboard.widgetLibrary') }}</p>
                            <h3 class="mt-2 text-xl font-semibold text-white">{{ t('dashboard.addWidgets') }}</h3>
                        </div>
                        <button class="text-sm text-white/55 hover:text-white" @click="showTemplateModal = false">{{ t('dashboard.close') }}</button>
                    </div>

                    <div class="dashboard-template-grid">
                        <button
                            v-for="template in widgetTemplates"
                            :key="template.type"
                            class="template-card"
                            type="button"
                            @click="addWidget(template)"
                        >
                            <div class="template-card__preview">
                                <template v-if="template.preview === 'skin'">
                                    <PlayerModel skinName="Steve" :width="140" :height="210" />
                                </template>

                                <template v-else-if="template.preview === 'inventory'">
                                    <div class="mini-inventory-preview">
                                        <div v-for="slot in 36" :key="slot" class="mini-inventory-slot" :class="{ 'mini-inventory-slot--filled': slot <= 12 }"></div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="widget-state-copy">{{ t('dashboard.previewUnavailable') }}</div>
                                </template>
                            </div>

                            <div class="template-card__body">
                                <div>
                                    <h4 class="text-sm font-semibold text-white">{{ template.name }}</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-white/60">{{ template.description }}</p>
                                </div>

                                <div class="mt-3 flex items-center justify-between text-[11px] text-white/45">
                                    <span>{{ t('dashboard.size') }} {{ template.default_size?.w ?? 1 }}x{{ template.default_size?.h ?? 1 }}</span>
                                    <span class="font-semibold text-profit">{{ t('dashboard.insert') }}</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-toolbar-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 14px;
}

.dashboard-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.dashboard-toolbar-settings {
    display: grid;
    gap: 6px;
    min-width: min(420px, calc(100vw - 120px));
    padding: 10px 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    background: rgba(7, 11, 18, 0.72);
    backdrop-filter: blur(10px);
}

.dashboard-toolbar-checks {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 14px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.8);
}

.dashboard-toolbar-checks label,
.dashboard-toolbar-checks > label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.dashboard-toolbar-spacer {
    flex: 1;
}

.dashboard-canvas {
    position: relative;
    display: grid;
    width: 100%;
    min-height: 720px;
    gap: 10px;
    padding: 6px;
    border-radius: 0;
    background: transparent;
    overflow: hidden;
}

.dashboard-grid-overlay {
    position: absolute;
    inset: 6px;
    display: grid;
    gap: 10px;
    pointer-events: none;
    z-index: 0;
}

.dashboard-grid-overlay span {
    border: 1px dashed rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
}

.dashboard-empty-state {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    pointer-events: none;
}

.dashboard-widget {
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 0;
    border: 1px solid transparent;
    background: transparent;
    box-shadow: none;
    padding: 0;
    overflow: visible;
    transition: transform 180ms cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1;
}

.dashboard-widget--editing:hover {
    transform: translateY(-1px);
    border-color: rgba(74, 222, 128, 0.8);
}

.dashboard-widget--editing {
    border-color: rgba(34, 197, 94, 0.45);
}

.dashboard-widget--pulse {
    animation: widgetPulse 240ms ease-out;
}

.dashboard-widget--dragging {
    transition: none;
    cursor: grabbing;
    filter: drop-shadow(0 16px 24px rgba(0, 0, 0, 0.32));
}

.dashboard-widget__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.dashboard-widget__actions {
    display: flex;
    gap: 8px;
}

.dashboard-widget__content {
    min-height: 0;
    flex: 1;
    overflow: hidden;
}

.dashboard-widget__settings {
    margin-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
    padding-top: 10px;
    border-radius: 12px;
    background: rgba(9, 15, 24, 0.58);
    padding: 10px;
    backdrop-filter: blur(6px);
}

.widget-field-label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.56);
}

.widget-input {
    width: 100%;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(8, 16, 28, 0.66);
    padding: 8px 10px;
    font-size: 12px;
    color: white;
}

.widget-input--compact {
    padding: 7px 10px;
}

.widget-input::placeholder {
    color: rgba(255, 255, 255, 0.44);
}

.widget-action {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.06);
    padding: 4px 10px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.84);
}

.widget-action--remove {
    border-color: rgba(251, 113, 133, 0.35);
    background: rgba(251, 113, 133, 0.12);
}

.widget-action--drag {
    cursor: grab;
}

.widget-action--drag:active {
    cursor: grabbing;
}

.widget-state-copy {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.65);
}

.widget-state-copy--center {
    text-align: center;
}

.widget-state-copy--error {
    color: rgba(253, 164, 175, 0.95);
}

.widget-profile-grid {
    display: grid;
    gap: 12px;
}

.widget-profile-grid__hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 12px;
    align-items: center;
}

.widget-profile-lines {
    min-width: 0;
}

.widget-profile-lines__name {
    font-size: 14px;
    font-weight: 700;
    color: white;
}

.widget-profile-lines__line {
    margin-top: 3px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.62);
}

.widget-skin-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.widget-inventory-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.widget-mini-inventory-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    overflow: hidden;
}

.widget-inventory-panel :deep(.inventory-grid),
.widget-mini-inventory-wrap :deep(.inventory-grid) {
    width: min(100%, var(--inv-w));
    max-width: 100%;
    background: transparent;
    border: 0;
    padding: clamp(3px, 0.45vw, 8px);
    gap: clamp(2px, 0.35vw, 6px);
}

.dashboard-drag-ghost {
    border-radius: 10px;
    border: 1px solid rgba(80, 170, 255, 0.5);
    background: rgba(80, 170, 255, 0.1);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
    pointer-events: none;
    z-index: 0;
}

.dashboard-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.09);
    background: rgba(255, 255, 255, 0.04);
    padding: 12px 14px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.84);
}

.dashboard-toggle-row--compact {
    padding: 8px 12px;
    min-width: 140px;
}

.visibility-chip {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.visibility-chip--public {
    border-color: rgba(16, 185, 129, 0.42);
    background: rgba(16, 185, 129, 0.12);
    color: #b7fbd6;
}

.visibility-chip--private {
    border-color: rgba(245, 158, 11, 0.36);
    background: rgba(245, 158, 11, 0.1);
    color: #fde68a;
}

.slot-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 14px;
}

.slot-chip {
    display: flex;
    flex-direction: column;
    gap: 2px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.09);
    background: rgba(255, 255, 255, 0.04);
    padding: 10px;
    text-align: left;
    color: rgba(255, 255, 255, 0.72);
}

.slot-chip--active {
    border-color: rgba(16, 185, 129, 0.42);
    background: rgba(16, 185, 129, 0.12);
    color: #bef7d4;
}

.slot-chip--locked {
    opacity: 0.42;
}

.apple-primary-button,
.apple-secondary-button,
.apple-save-button,
.apple-control-button {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.05));
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.92);
    transition: transform 150ms ease, border-color 150ms ease, background 150ms ease;
}

.apple-primary-button:hover,
.apple-secondary-button:hover,
.apple-save-button:hover,
.apple-control-button:hover {
    transform: translateY(-1px);
    border-color: rgba(255, 255, 255, 0.22);
}

.apple-primary-button:disabled,
.apple-secondary-button:disabled,
.apple-save-button:disabled,
.apple-control-button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
    transform: none;
}

.apple-primary-button {
    border-color: rgba(96, 165, 250, 0.36);
    background: linear-gradient(180deg, rgba(96, 165, 250, 0.22), rgba(96, 165, 250, 0.12));
}

.apple-secondary-button {
    border-color: rgba(148, 163, 184, 0.38);
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.2), rgba(148, 163, 184, 0.1));
}

.apple-save-button {
    border-color: rgba(16, 185, 129, 0.36);
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.24), rgba(16, 185, 129, 0.12));
    color: #b7fbd6;
}

.dashboard-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(4, 6, 10, 0.7);
    backdrop-filter: blur(8px);
}

.dashboard-modal {
    width: min(980px, calc(100vw - 28px));
    max-height: min(82vh, 820px);
    overflow: auto;
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(13, 17, 24, 0.95);
    padding: 22px;
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.56);
}

.dashboard-template-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.template-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-radius: 22px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.025));
    padding: 14px;
    text-align: left;
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.template-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.075), rgba(255, 255, 255, 0.03));
}

.template-card__preview {
    min-height: 220px;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(8, 12, 18, 0.62);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.template-card__body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 92px;
}

.mini-inventory-preview {
    display: grid;
    grid-template-columns: repeat(9, 1fr);
    gap: 4px;
    width: 100%;
    padding: 12px;
}

.mini-inventory-slot {
    aspect-ratio: 1;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
}

.mini-inventory-slot--filled {
    background: linear-gradient(180deg, rgba(96, 165, 250, 0.35), rgba(96, 165, 250, 0.12));
    border-color: rgba(96, 165, 250, 0.36);
}

.fade-scale-enter-active,
.fade-scale-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

@keyframes widgetPulse {
    0% {
        transform: scale(0.995);
    }

    60% {
        transform: scale(1.01);
    }

    100% {
        transform: scale(1);
    }
}

@media (max-width: 1024px) {
    .dashboard-template-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-canvas {
        min-height: 620px;
    }
}
</style>
