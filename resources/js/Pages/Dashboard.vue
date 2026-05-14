<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlayerModel from '@/Components/SkyBlock/PlayerModel.vue';
import InventoryGrid from '@/Components/SkyBlock/InventoryGrid.vue';
import ItemSlot from '@/Components/SkyBlock/ItemSlot.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/strings/useI18n';
import { getHeadUrl } from '@/utils/textures';
import { DASHBOARD_SKILL_ICON_ITEMS, DASHBOARD_SLAYER_ICON_ITEMS } from '@/lib/dashboardProfileIcons';
import {
    buildDashboardTimerCards,
    formatDurationSeconds,
    getEventsForSkyDate,
    getSelectableTimerEventOptions,
    getSkyblockDateFromMs,
} from '@/lib/dashboardSkyblockWidgets';

const { t } = useI18n();

/** Same surface treatment as the Profile Stats hero search field (`ProfileStats/Index.vue`). */
const DASH_TOOLBAR_FIELD_CLASS =
    'rounded-xl border border-border/80 bg-surface-800/80 py-3 px-3 text-sm text-white shadow-none transition placeholder:text-neutral/80 focus:border-profit/70 focus:outline-none focus:ring-2 focus:ring-profit/25';

const DASH_TOOLBAR_FILTER_TRIGGER_CLASS =
    'dash-toolbar-summary list-none cursor-pointer select-none whitespace-nowrap rounded-xl border border-border/80 bg-surface-800/80 px-3 py-3 text-sm font-semibold text-white shadow-none backdrop-blur-sm transition hover:border-border focus-visible:border-profit/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-profit/25';

/** Outer search card on Profile Stats uses `bg-surface-900/75` + blur + shadow; panels match that. */
const DASH_TOOLBAR_FILTER_PANEL_CLASS =
    'dash-toolbar-panel rounded-2xl border border-border/80 bg-surface-900/75 p-3 shadow-[0_16px_40px_rgba(0,0,0,0.35)] backdrop-blur-sm';

const props = defineProps({
    canEditDashboard: { type: Boolean, default: false },
    requiresLogin: { type: Boolean, default: true },
    requiresMinecraftLink: { type: Boolean, default: false },
    dashboard: { type: Object, default: null },
    widgetTemplates: { type: Array, default: () => [] },
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
const saveDebounceTimer = ref(null);
const AUTOSAVE_DEBOUNCE_MS = 750;
const pendingCloseEditAfterSave = ref(false);

/** Must match `.dashboard-canvas` `gap` in scoped CSS. */
const DASHBOARD_GRID_GAP_PX = 10;

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

const textureVersion = ref(0);
provide('textureVersion', textureVersion);

const dashboardSnapshot = ref(null);
const dashboardSnapshotLoading = ref(false);
const timerNowMs = ref(Date.now());
let timerTickId = null;
const bazaarTopRows = ref([]);
const bazaarTopLoading = ref(false);
const leaderboardLookupByKey = ref({});

const TIMER_EVENT_SELECT_OPTIONS = getSelectableTimerEventOptions();

const LEADERBOARD_SORT_OPTIONS = [
    { key: 'level', label: 'Level' },
    { key: 'networth', label: 'Networth' },
    { key: 'non_cosmetic_networth', label: 'Pure coins' },
    { key: 'skill_average', label: 'Skill avg' },
    { key: 'slayer_total', label: 'Slayer XP' },
    { key: 'weight', label: 'Weight' },
];

const widgets = ref((props.dashboard?.widgets ?? []).map((widget) => normalizeWidget(widget)));

const profileWidgetTypes = new Set([
    'skin_view_widget',
    'inventory_gui_widget',
    'profile_skills_widget',
    'profile_slayers_widget',
    'profile_collections_widget',
    'profile_networth_widget',
    'profile_pets_widget',
    'profile_equipment_widget',
    'profile_armor_widget',
    'profile_weapons_widget',
]);

const serverSnapshotWidgetTypes = new Set(['event_timers_widget', 'skyblock_calendar_widget', 'mayor_status_widget']);

const MAIN_SKILL_NAMES = ['farming', 'mining', 'combat', 'foraging', 'fishing', 'enchanting'];
const SECONDARY_SKILL_NAMES = ['alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting'];

const petTierColors = {
    COMMON: '#AAAAAA',
    UNCOMMON: '#55FF55',
    RARE: '#5555FF',
    EPIC: '#AA00AA',
    LEGENDARY: '#FFAA00',
    MYTHIC: '#FF55FF',
};
const totalGridCells = computed(() => gridColumns.value * gridRows.value);
const selectedWidget = computed(() => widgets.value.find((widget) => widget.clientId === selectedWidgetId.value) ?? null);

function slayerBossOptionsForWidget(w) {
    if (!w || w.type !== 'profile_slayers_widget') {
        return [];
    }

    const all = slayerEntriesForWidget(w);
    const keySet = new Set(Object.keys(DASHBOARD_SLAYER_ICON_ITEMS));
    for (const row of all) {
        keySet.add(row.key);
    }

    return [...keySet]
        .map((key) => {
            const row = all.find((r) => r.key === key);
            const label = row?.name ?? DASHBOARD_SLAYER_ICON_ITEMS[key]?.name ?? key;

            return { key, label };
        })
        .sort((a, b) => a.label.localeCompare(b.label));
}

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

function templateForType(type) {
    return templateMap.value[type] ?? null;
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

/** Bump legacy saved sizes to match current template minimums / orientation. */
function remapLegacyWidgetDimensions(type, w, h) {
    let nw = w;
    let nh = h;

    if (type === 'leaderboard_rank_widget' && w === 2 && h === 3) {
        nw = 3;
        nh = 2;
    } else if (type === 'skin_view_widget' && w === 3 && h === 5) {
        nw = 3;
        nh = 4;
    } else if (type === 'inventory_gui_widget' && w === 8 && h === 5) {
        nw = 8;
        nh = 4;
    } else if (type === 'profile_slayers_widget' && w === 8 && h === 5) {
        nw = 8;
        nh = 4;
    } else if (type === 'profile_weapons_widget' && w === 8 && h === 2) {
        nw = 4;
        nh = 1;
    } else if (type === 'profile_weapons_widget' && w === 4 && h === 2) {
        nw = 4;
        nh = 1;
    } else if (type === 'profile_networth_widget' && w < 6 && h === 3) {
        nw = 6;
        nh = 3;
    } else if (type === 'profile_pets_widget' && (w < 6 || h < 5)) {
        nw = Math.max(w, 6);
        nh = Math.max(h, 5);
    }

    return { w: nw, h: nh };
}

function migrateWidgetSettings(type, raw) {
    if (type === 'profile_slayers_widget') {
        if (!Array.isArray(raw.slayer_selected_keys)) {
            raw.slayer_selected_keys = [];
        }

        if (raw.slayer_display_mode === 'single' && raw.slayer_boss) {
            raw.slayer_selected_keys = [String(raw.slayer_boss)];
        }

        delete raw.slayer_display_mode;
        delete raw.slayer_boss;
        delete raw.slayer_visible_count;
    }

    if (type === 'profile_skills_widget') {
        if (!Array.isArray(raw.skill_selected_keys)) {
            raw.skill_selected_keys = [];
        }
    }

    if (type === 'profile_collections_widget') {
        if (!Array.isArray(raw.collection_selected_keys)) {
            raw.collection_selected_keys = [];
        }
    }
}

function normalizeWidget(widget) {
    const template = templateForType(widget.type);
    const defW = Number(template?.default_size?.w ?? 2);
    const defH = Number(template?.default_size?.h ?? 2);
    const minW = Number(template?.min_size?.w ?? 1);
    const minH = Number(template?.min_size?.h ?? 1);
    let w = Number(widget.w);
    let h = Number(widget.h);
    if (!Number.isFinite(w)) {
        w = defW;
    }
    if (!Number.isFinite(h)) {
        h = defH;
    }
    w = clamp(w, minW, gridColumns.value);
    h = clamp(h, minH, gridRows.value);

    const remapped = remapLegacyWidgetDimensions(widget.type, w, h);
    w = clamp(remapped.w, minW, gridColumns.value);
    h = clamp(remapped.h, minH, gridRows.value);

    const rawSettings = { ...(template?.default_settings ?? {}), ...(widget.settings ?? {}) };
    if (widget.type === 'inventory_gui_widget' && 'show_hotbar' in rawSettings) {
        delete rawSettings.show_hotbar;
    }

    migrateWidgetSettings(widget.type, rawSettings);

    return {
        id: widget.id ?? null,
        clientId: `widget-${widget.id ?? `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`}`,
        type: widget.type,
        title: widget.title || template?.default_title || 'Widget',
        x: Number(widget.x ?? 1),
        y: Number(widget.y ?? 1),
        w,
        h,
        settings: rawSettings,
        pulse: false,
    };
}

function measureCanvas() {
    if (!gridRef.value) {
        return;
    }

    const el = gridRef.value;
    const rect = el.getBoundingClientRect();
    let padX = 0;
    let padY = 0;

    if (typeof window !== 'undefined' && typeof window.getComputedStyle === 'function') {
        const cs = window.getComputedStyle(el);
        padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
        padY = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
    }

    gridBounds.value = {
        width: Math.max(0, rect.width - padX),
        height: Math.max(0, rect.height - padY),
    };
}

function scheduleMeasureCanvas() {
    measureCanvas();
    if (typeof requestAnimationFrame !== 'undefined') {
        requestAnimationFrame(() => {
            measureCanvas();
        });
    }
}

/** Pixel size of one grid cell; `gridBounds` must exclude padding. */
function gridCellPixelSize() {
    const bw = gridBounds.value.width;
    const bh = gridBounds.value.height;
    const cols = gridColumns.value;
    const rows = gridRows.value;

    if (!bw || !bh || cols < 1 || rows < 1) {
        return null;
    }

    const g = DASHBOARD_GRID_GAP_PX;
    const cellW = (bw - g * (cols - 1)) / cols;
    const cellH = (bh - g * (rows - 1)) / rows;

    if (!(cellW > 0) || !(cellH > 0)) {
        return null;
    }

    return { cellW, cellH, gap: g };
}

/** Total pixel width/height of a widget spanning `widget.w` × `widget.h` cells (includes internal gaps). */
function widgetAreaPixels(widget) {
    const m = gridCellPixelSize();

    if (!m) {
        return { width: 0, height: 0 };
    }

    const { cellW, cellH, gap } = m;
    const w = Number(widget.w ?? 1);
    const h = Number(widget.h ?? 1);
    const width = cellW * w + gap * Math.max(0, w - 1);
    const height = cellH * h + gap * Math.max(0, h - 1);

    return { width, height };
}

function vibrate(duration = 6) {
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        navigator.vibrate(duration);
    }
}

function scheduleAutosave() {
    if (!props.canEditDashboard || !editMode.value) {
        return;
    }

    if (saveDebounceTimer.value) {
        window.clearTimeout(saveDebounceTimer.value);
    }

    saveDebounceTimer.value = window.setTimeout(() => {
        saveDebounceTimer.value = null;
        if (isDirty.value && !isSaving.value) {
            saveDashboard();
        }
    }, AUTOSAVE_DEBOUNCE_MS);
}

function markDirty() {
    isDirty.value = true;
    feedback.value = '';
    scheduleAutosave();
}

function toggleDashboardVisibility() {
    if (!props.canEditDashboard || !editMode.value) {
        return;
    }

    isPublic.value = !isPublic.value;
    markDirty();
}

function clearFeedbackSoon(message) {
    feedback.value = message;
    window.setTimeout(() => {
        if (feedback.value === message) {
            feedback.value = '';
        }
    }, 2200);
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

    const cellMetrics = gridCellPixelSize();
    if (!cellMetrics) {
        return;
    }

    const { cellW: cellWidth, cellH: cellHeight } = cellMetrics;
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

function onWidgetPointerDown(widget, event) {
    if (!props.canEditDashboard || !editMode.value) {
        return;
    }

    if (event.target.closest('.widget-delete-btn')) {
        return;
    }

    selectWidget(widget.clientId);
    beginWidgetInteraction('drag', widget, event);
}

function inventoryGridStyle(widget) {
    const area = widgetAreaPixels(widget);
    if (!area.width || !area.height) {
        return { '--inv-w': '220px' };
    }

    const rows = 5;
    const usableWidth = Math.max(140, area.width - 28);
    const usableHeight = Math.max(92, area.height - (editMode.value ? 40 : 28));
    const widthFromHeight = (usableHeight / rows) * 9;
    const target = Math.max(128, Math.min(usableWidth, widthFromHeight)) * 0.92;

    return { '--inv-w': `${Math.floor(target)}px` };
}

function skinModelSize(widget) {
    const area = widgetAreaPixels(widget);
    if (!area.width || !area.height) {
        return { width: 124, height: 206 };
    }

    const maxWidth = Math.max(96, Math.min(area.width - 18, 420));
    const maxHeight = Math.max(160, Math.min(area.height - (editMode.value ? 36 : 20), 640));
    const width = Math.min(maxWidth, maxHeight * 0.58);
    const height = Math.min(maxHeight, width * 1.7);

    return {
        width: Math.floor(Math.max(96, width)),
        height: Math.floor(Math.max(160, height)),
    };
}

function finishLeaveEditMode() {
    showTemplateModal.value = false;
    selectedWidgetId.value = null;
    dragGhost.value = null;
    layoutUndoStack.value = [];
    initialEditSnapshot.value = null;
    editMode.value = false;
}

function toggleEditMode() {
    if (!canOpenEdit.value) {
        return;
    }

    if (editMode.value) {
        if (saveDebounceTimer.value) {
            window.clearTimeout(saveDebounceTimer.value);
            saveDebounceTimer.value = null;
        }

        if (isSaving.value) {
            pendingCloseEditAfterSave.value = true;

            return;
        }

        if (isDirty.value && props.canEditDashboard) {
            saveDashboard({ closeEditAfter: true });

            return;
        }

        finishLeaveEditMode();

        return;
    }

    pendingCloseEditAfterSave.value = false;
    editMode.value = true;
    initialEditSnapshot.value = currentLayoutSnapshot();
    layoutUndoStack.value = [];
    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
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

    if (template.type === 'profile_slayers_widget' || template.type === 'profile_collections_widget' || template.type === 'profile_skills_widget') {
        nextTick(() => syncDynamicProfileWidgetHeights({ persist: false }));
    }

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

function saveDashboard(options = {}) {
    const closeEditAfter = Boolean(options.closeEditAfter);

    if (!props.canEditDashboard || isSaving.value) {
        return;
    }

    if (!closeEditAfter && !editMode.value) {
        return;
    }

    if (!isDirty.value) {
        if (closeEditAfter) {
            finishLeaveEditMode();
        }

        return;
    }

    isSaving.value = true;

    router.post(route('dashboard.save'), {
        is_public: isPublic.value,
        widgets: widgets.value.map((widget) => ({
            id: widget.id,
            type: widget.type,
            title: widget.title,
            x: widget.x,
            y: widget.y,
            w: Number(widget.w),
            h: Number(widget.h),
            settings: widget.settings,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isDirty.value = false;
            if (closeEditAfter || pendingCloseEditAfterSave.value) {
                pendingCloseEditAfterSave.value = false;
                finishLeaveEditMode();
            }
            clearFeedbackSoon(t('dashboard.saved'));
            vibrate(10);
        },
        onError: () => {
            pendingCloseEditAfterSave.value = false;
            clearFeedbackSoon(t('dashboard.saveFailed'));
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
        const response = await fetch(`/api/profile/minecraft/${encodeURIComponent(username)}`);
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

function leaderboardSortLabel(widget) {
    const k = String(widget.settings?.sort ?? 'level');

    return LEADERBOARD_SORT_OPTIONS.find((o) => o.key === k)?.label ?? k;
}

function widgetStatusText(widget) {
    if (widget.type === 'leaderboard_rank_widget') {
        return '';
    }

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

function capitalize(str) {
    if (!str || typeof str !== 'string') {
        return '';
    }

    return str.charAt(0).toUpperCase() + str.slice(1);
}

function widgetCurrentData(widget) {
    return widgetProfileData(widget)?.currentData ?? null;
}

function skillOptionsForWidget(w) {
    if (!w || w.type !== 'profile_skills_widget') {
        return [];
    }

    const skills = widgetCurrentData(w)?.skills;
    if (!skills || typeof skills !== 'object') {
        return [];
    }

    return [...MAIN_SKILL_NAMES, ...SECONDARY_SKILL_NAMES]
        .filter((name) => skills[name])
        .map((name) => ({
            key: name,
            label: capitalize(name),
        }));
}

function skillRowsForWidget(widget) {
    const skills = widgetCurrentData(widget)?.skills;
    if (!skills || typeof skills !== 'object') {
        return [];
    }

    const ordered = [...MAIN_SKILL_NAMES, ...SECONDARY_SKILL_NAMES]
        .filter((name) => skills[name])
        .map((name) => ({ name, ...skills[name] }));

    const pick = coerceStringArray(widget.settings?.skill_selected_keys);
    if (pick.length === 0) {
        return ordered;
    }

    const allNames = ordered.map((r) => r.name);
    if (pick.length >= allNames.length && allNames.every((n) => pick.includes(n))) {
        return ordered;
    }

    const byName = new Map(ordered.map((r) => [r.name, r]));

    return pick.map((n) => byName.get(n)).filter(Boolean);
}

function slayerEntriesForWidget(widget) {
    const sl = widgetCurrentData(widget)?.slayers;
    const bosses = sl?.slayers;
    if (!bosses || typeof bosses !== 'object') {
        return [];
    }

    return Object.entries(bosses).map(([key, data]) => ({ key, ...data }));
}

function slayerRowsForWidget(widget) {
    const all = slayerEntriesForWidget(widget);
    if (all.length === 0) {
        return [];
    }

    const pick = coerceStringArray(widget.settings?.slayer_selected_keys);
    if (pick.length === 0) {
        return all;
    }

    const allKeys = all.map((r) => r.key);
    if (pick.length >= allKeys.length && allKeys.every((k) => pick.includes(k))) {
        return all;
    }

    const byKey = new Map(all.map((r) => [r.key, r]));

    return pick.map((k) => byKey.get(k)).filter(Boolean);
}

function coerceStringArray(val) {
    if (!Array.isArray(val)) {
        return [];
    }

    return val.map((x) => String(x)).filter(Boolean);
}

function collectionRowsForWidget(widget) {
    const cats = widgetCurrentData(widget)?.collections?.categories;
    if (!cats || typeof cats !== 'object') {
        return [];
    }

    const pick = coerceStringArray(widget.settings?.collection_selected_keys);
    if (pick.length === 0) {
        return [];
    }

    const rows = [];
    for (const token of pick) {
        const sep = token.indexOf(':');
        const catId = sep >= 0 ? token.slice(0, sep) : '';
        const itemId = sep >= 0 ? token.slice(sep + 1) : token;
        const cat = cats[catId];
        const list = Array.isArray(cat?.collections) ? cat.collections : [];
        const item = list.find((c) => String(c.id) === String(itemId));
        if (item) {
            rows.push({
                ...item,
                categoryId: catId,
                categoryName: cat?.name ?? catId,
            });
        }
    }

    return rows;
}

const ROMAN_COLLECTION_TIER = ['0', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

function romanCollectionTier(n) {
    const i = Number(n);
    if (!Number.isFinite(i) || i < 0 || i >= ROMAN_COLLECTION_TIER.length) {
        return String(n);
    }

    return ROMAN_COLLECTION_TIER[i];
}

function slayerWidgetTargetHeight(widget) {
    const tmpl = templateForType('profile_slayers_widget');
    const minH = Number(tmpl?.min_size?.h ?? 3);
    const n = slayerRowsForWidget(widget).length;
    const showTotal = n > 0 && widgetCurrentData(widget)?.slayers?.total_slayer_xp != null;
    const bodyRows = n === 0 ? 1 : n;

    return clamp(bodyRows + (showTotal ? 1 : 0), minH, gridRows.value);
}

function skillWidgetTargetHeight(widget) {
    const tmpl = templateForType('profile_skills_widget');
    const minH = Number(tmpl?.min_size?.h ?? 4);
    const n = skillRowsForWidget(widget).length;
    const bodyRows = n === 0 ? 1 : n;

    return clamp(bodyRows, minH, gridRows.value);
}

function collectionsWidgetTargetHeight(widget) {
    const tmpl = templateForType('profile_collections_widget');
    const minH = Number(tmpl?.min_size?.h ?? 3);
    const n = collectionRowsForWidget(widget).length;
    if (n === 0) {
        return Math.max(minH, 4);
    }

    return clamp(n, minH, 18);
}

function syncDynamicProfileWidgetHeights(options = {}) {
    const persist = Boolean(options.persist);
    let changed = false;
    for (const widget of widgets.value) {
        if (widget.type === 'profile_slayers_widget') {
            const next = slayerWidgetTargetHeight(widget);
            if (Number(widget.h) !== next) {
                widget.h = next;
                changed = true;
            }
        } else if (widget.type === 'profile_skills_widget') {
            const next = skillWidgetTargetHeight(widget);
            if (Number(widget.h) !== next) {
                widget.h = next;
                changed = true;
            }
        } else if (widget.type === 'profile_collections_widget') {
            const next = collectionsWidgetTargetHeight(widget);
            if (Number(widget.h) !== next) {
                widget.h = next;
                changed = true;
            }
        }
    }

    if (changed && persist && props.canEditDashboard && editMode.value) {
        markDirty();
    }
}

function collectionPickerGroupsForWidget(w) {
    if (!w || w.type !== 'profile_collections_widget') {
        return [];
    }

    const data = widgetProfileData(w);
    const cats = data?.currentData?.collections?.categories;
    if (!cats || typeof cats !== 'object') {
        return [];
    }

    return Object.entries(cats)
        .map(([catId, cat]) => ({
            id: catId,
            name: cat?.name ?? catId,
            items: Array.isArray(cat?.collections)
                ? cat.collections.map((item) => ({
                    key: `${catId}:${item.id}`,
                    label: item.name ?? item.id,
                    maxed: Boolean(item.maxed),
                }))
                : [],
        }))
        .filter((g) => g.items.length > 0);
}

function slayerKeyChecked(w, key) {
    if (!w || w.type !== 'profile_slayers_widget') {
        return false;
    }

    const all = slayerEntriesForWidget(w).map((r) => r.key);
    const keys = coerceStringArray(w.settings?.slayer_selected_keys);
    if (keys.length === 0) {
        return true;
    }

    if (all.length > 0 && keys.length >= all.length && all.every((k) => keys.includes(k))) {
        return true;
    }

    return keys.includes(key);
}

function toggleSlayerKey(w, key) {
    if (!w || w.type !== 'profile_slayers_widget') {
        return;
    }

    const all = slayerEntriesForWidget(w).map((r) => r.key);
    if (all.length === 0) {
        return;
    }

    let keys = coerceStringArray(w.settings.slayer_selected_keys);
    if (keys.length === 0 || (keys.length >= all.length && all.every((k) => keys.includes(k)))) {
        keys = [...all];
    }

    if (keys.includes(key)) {
        keys = keys.filter((k) => k !== key);
    } else {
        keys = [...keys, key];
    }

    if (keys.length === 0 || (all.length > 0 && keys.length >= all.length && all.every((k) => keys.includes(k)))) {
        w.settings.slayer_selected_keys = [];
    } else {
        w.settings.slayer_selected_keys = keys;
    }

    markDirty();
    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
}

function skillKeyChecked(w, key) {
    if (!w || w.type !== 'profile_skills_widget') {
        return false;
    }

    const all = skillOptionsForWidget(w).map((o) => o.key);
    const keys = coerceStringArray(w.settings?.skill_selected_keys);
    if (keys.length === 0) {
        return true;
    }

    if (all.length > 0 && keys.length >= all.length && all.every((k) => keys.includes(k))) {
        return true;
    }

    return keys.includes(key);
}

function toggleSkillKey(w, key) {
    if (!w || w.type !== 'profile_skills_widget') {
        return;
    }

    const all = skillOptionsForWidget(w).map((o) => o.key);
    if (all.length === 0) {
        return;
    }

    let keys = coerceStringArray(w.settings.skill_selected_keys);
    if (keys.length === 0 || (keys.length >= all.length && all.every((k) => keys.includes(k)))) {
        keys = [...all];
    }

    if (keys.includes(key)) {
        keys = keys.filter((k) => k !== key);
    } else {
        keys = [...keys, key];
    }

    if (keys.length === 0 || (all.length > 0 && keys.length >= all.length && all.every((k) => keys.includes(k)))) {
        w.settings.skill_selected_keys = [];
    } else {
        w.settings.skill_selected_keys = keys;
    }

    markDirty();
    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
}

function collectionKeyChecked(w, token) {
    if (!w || w.type !== 'profile_collections_widget') {
        return false;
    }

    return coerceStringArray(w.settings?.collection_selected_keys).includes(token);
}

function toggleCollectionKey(w, token) {
    if (!w || w.type !== 'profile_collections_widget') {
        return;
    }

    if (!Array.isArray(w.settings.collection_selected_keys)) {
        w.settings.collection_selected_keys = [];
    }

    const keys = [...coerceStringArray(w.settings.collection_selected_keys)];
    const i = keys.indexOf(token);
    if (i >= 0) {
        keys.splice(i, 1);
    } else {
        keys.push(token);
    }

    w.settings.collection_selected_keys = keys;
    markDirty();
    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
}

function clearCollectionSelection(w) {
    if (!w || w.type !== 'profile_collections_widget') {
        return;
    }

    w.settings.collection_selected_keys = [];
    markDirty();
    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
}

function widgetEventTimerCard(widget) {
    const snap = dashboardSnapshot.value;
    if (!snap?.perk_state) {
        return null;
    }

    const want = String(widget.settings?.event_timer_key ?? 'dark-auction').trim();
    const cards = buildDashboardTimerCards(timerNowMs.value, snap.election_timeline, snap.perk_state);

    return cards.find((c) => c.key === want) ?? null;
}

function eventRingStrokeOffset(progress) {
    const clamped = Math.max(0, Math.min(1, Number(progress) || 0));
    const r = 36;
    const circumference = 2 * Math.PI * r;

    return circumference * (1 - clamped);
}

function formatEventClock(unixSeconds) {
    const u = Number(unixSeconds);
    if (!Number.isFinite(u)) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        }).format(new Date(u * 1000));
    } catch {
        return '—';
    }
}

function formatDashboardCoins(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    if (n >= 1e12) {
        return `${(n / 1e12).toFixed(2)}T`;
    }

    if (n >= 1e9) {
        return `${(n / 1e9).toFixed(2)}B`;
    }

    if (n >= 1e6) {
        return `${(n / 1e6).toFixed(2)}M`;
    }

    if (n >= 1e3) {
        return `${(n / 1e3).toFixed(1)}K`;
    }

    return Math.floor(n).toLocaleString();
}

function petsPreviewList(widget) {
    const pets = widgetCurrentData(widget)?.pets;
    const list = pets?.uniquePets ?? pets?.pets ?? [];
    if (!Array.isArray(list)) {
        return [];
    }

    return list.slice(0, 30);
}

function equipmentSlotsForWidget(widget) {
    const equip = widgetCurrentData(widget)?.equipment;
    if (!Array.isArray(equip) || equip.length === 0) {
        return [];
    }

    return [...equip].reverse();
}

function armorSlotsForWidget(widget) {
    const arm = widgetCurrentData(widget)?.armor;
    if (!Array.isArray(arm) || arm.length === 0) {
        return [];
    }

    return [...arm].reverse();
}

function weaponsListForWidget(widget) {
    const list = widgetCurrentData(widget)?.weapons;
    if (!Array.isArray(list)) {
        return [];
    }

    return list.slice(0, 12);
}

function skillIconItem(skillName) {
    return DASHBOARD_SKILL_ICON_ITEMS[skillName] ?? null;
}

function slayerIconItem(key) {
    return DASHBOARD_SLAYER_ICON_ITEMS[key] ?? { skyblock_id: 'BONE', rarity: 'common', name: '' };
}

function widgetsUseServerSnapshot() {
    return widgets.value.some((w) => serverSnapshotWidgetTypes.has(w.type));
}

function widgetsUseBazaar() {
    return widgets.value.some((w) => w.type === 'bazaar_top_widget');
}

function widgetsUseLeaderboard() {
    return widgets.value.some((w) => w.type === 'leaderboard_rank_widget');
}

function leaderboardLookupKey(widget) {
    const sort = String(widget.settings?.sort ?? 'level');

    return `${sort}:${(widget.settings?.username ?? '').trim().toLowerCase()}`;
}

async function refreshDashboardSnapshot() {
    if (!widgetsUseServerSnapshot()) {
        return;
    }

    dashboardSnapshotLoading.value = true;
    try {
        const res = await fetch('/api/v1/dashboard/snapshot');
        const json = await res.json();
        if (res.ok) {
            dashboardSnapshot.value = json;
            textureVersion.value++;
        }
    } catch {
        /* ignore */
    } finally {
        dashboardSnapshotLoading.value = false;
    }
}

async function refreshBazaarTop() {
    if (!widgetsUseBazaar()) {
        return;
    }

    bazaarTopLoading.value = true;
    try {
        const res = await fetch('/api/v1/bazaar/live');
        const rows = await res.json();
        if (!Array.isArray(rows)) {
            bazaarTopRows.value = [];

            return;
        }

        const sorted = [...rows].sort((a, b) => Number(b.margin ?? 0) - Number(a.margin ?? 0));
        const bw = widgets.value.find((x) => x.type === 'bazaar_top_widget');
        const limit = Math.min(20, Math.max(4, Number(bw?.settings?.limit ?? 8)));
        bazaarTopRows.value = sorted.slice(0, limit);
    } catch {
        bazaarTopRows.value = [];
    } finally {
        bazaarTopLoading.value = false;
    }
}

async function refreshLeaderboardWidgets() {
    if (!widgetsUseLeaderboard()) {
        leaderboardLookupByKey.value = {};

        return;
    }

    const next = { ...leaderboardLookupByKey.value };
    const targets = widgets.value.filter((w) => w.type === 'leaderboard_rank_widget');

    for (const w of targets) {
        const q = (w.settings?.username ?? '').trim();
        const key = leaderboardLookupKey(w);

        if (q.length < 2) {
            next[key] = { error: 'short' };
            continue;
        }

        const sort = String(w.settings?.sort ?? 'level');

        try {
            const params = new URLSearchParams({ q, sort, direction: 'desc', filter: 'all' });
            const res = await fetch(`/api/v1/leaderboards/lookup?${params.toString()}`);
            const body = await res.json();
            const data = body?.data;
            if (data?.found) {
                next[key] = { rank: data.rank, name: data.display_name ?? data.profile_username };
            } else {
                next[key] = { error: 'not_found' };
            }
        } catch {
            next[key] = { error: 'fetch' };
        }
    }

    leaderboardLookupByKey.value = next;
}

const dashboardCalendar = computed(() => {
    const snap = dashboardSnapshot.value;
    if (!snap?.perk_state) {
        return null;
    }

    const d = getSkyblockDateFromMs(timerNowMs.value);
    const events = getEventsForSkyDate(d.day, d.month, d.year, snap.perk_state.active_perks ?? {});

    return { ...d, events };
});

const serverWidgetsSignature = computed(() =>
    widgets.value
        .filter(
            (w) =>
                serverSnapshotWidgetTypes.has(w.type) ||
                w.type === 'bazaar_top_widget' ||
                w.type === 'leaderboard_rank_widget'
        )
        .map((w) => `${w.type}:${w.clientId}:${JSON.stringify(w.settings ?? {})}`)
        .join('|')
);

watch(serverWidgetsSignature, () => {
    refreshDashboardSnapshot();
    refreshBazaarTop();
    refreshLeaderboardWidgets();
}, { immediate: true });

const selectedWidgetNeedsUsername = computed(() => {
    const w = selectedWidget.value;
    if (!w) {
        return false;
    }

    return profileWidgetTypes.has(w.type) || w.type === 'leaderboard_rank_widget';
});

const profileSignature = computed(() =>
    widgets.value
        .filter((widget) => profileWidgetTypes.has(widget.type))
        .map((widget) => `${widget.type}:${(widget.settings?.username ?? '').trim().toLowerCase()}`)
        .join('|')
);

watch(profileSignature, () => {
    refreshLiveProfiles();
}, { immediate: true });

watch(
    profilePayloadByUsername,
    () => {
        nextTick(() => syncDynamicProfileWidgetHeights({ persist: false }));
    },
    { deep: true }
);

watch(
    () => props.dashboard,
    (next) => {
        if (!next) {
            return;
        }

        if (editMode.value && isDirty.value) {
            return;
        }

        isPublic.value = Boolean(next.is_public);
        widgets.value = (next.widgets ?? []).map((widget) => normalizeWidget(widget));
        nextTick(() => syncDynamicProfileWidgetHeights({ persist: false }));
    },
    { deep: true }
);

onMounted(() => {
    measureCanvas();
    refreshLiveProfiles();

    timerTickId = window.setInterval(() => {
        timerNowMs.value = Date.now();
    }, 1000);

    if (gridRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver.value = new ResizeObserver(() => {
            scheduleMeasureCanvas();
        });

        resizeObserver.value.observe(gridRef.value);
    }

    window.addEventListener('resize', scheduleMeasureCanvas);

    nextTick(() => {
        syncDynamicProfileWidgetHeights({ persist: false });
    });
});

onBeforeUnmount(() => {
    if (timerTickId != null) {
        window.clearInterval(timerTickId);
        timerTickId = null;
    }

    if (saveDebounceTimer.value) {
        window.clearTimeout(saveDebounceTimer.value);
        saveDebounceTimer.value = null;
    }

    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }

    window.removeEventListener('resize', scheduleMeasureCanvas);
    window.removeEventListener('pointermove', onGlobalPointerMove);
    window.removeEventListener('pointerup', onGlobalPointerUp);
});
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="space-y-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-neutral">{{ t('dashboard.kicker') }}</p>
                        <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                                <h1 class="min-w-0 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ t('dashboard.heading') }}</h1>
                                <span
                                    class="inline-flex shrink-0 items-center rounded-md border border-amber-400/45 bg-amber-500/12 px-2 py-0.5 text-xs font-semibold tracking-tight text-amber-100/95"
                                >
                                    {{ t('dashboard.demoTag') }}
                                </span>
                            </div>
                            <button
                                v-if="!editMode"
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-45"
                                :class="canOpenEdit ? 'border border-profit/35 bg-profit/20 text-profit hover:bg-profit/30 hover:text-white' : 'border border-border/60 bg-surface-800/50 text-neutral'"
                                :disabled="!canOpenEdit || isSaving"
                                @click="toggleEditMode"
                            >
                                {{ t('dashboard.edit') }}
                            </button>
                        </div>
                    </div>

                    <p v-if="requiresLogin" class="rounded-xl border border-amber-400/35 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                        {{ t('dashboard.loginRequired') }}
                        <a :href="route('auth.discord')" class="ml-1 font-semibold underline">{{ t('dashboard.loginDiscord') }}</a>
                    </p>

                    <p v-else-if="requiresMinecraftLink" class="rounded-xl border border-indigo-400/35 bg-indigo-400/10 px-4 py-3 text-sm text-indigo-100">
                        {{ t('dashboard.mcRequired') }}
                        <Link :href="route('profile.edit')" class="ml-1 font-semibold underline">{{ t('dashboard.openProfileSettings') }}</Link>
                    </p>

                    <div v-if="feedback" class="inline-flex rounded-full border border-border/80 bg-surface-800/70 px-3 py-1 text-sm text-white/85">
                        {{ feedback }}
                    </div>
                </div>

                <div>
                    <div
                        v-if="editMode"
                        class="mb-4 flex min-w-0 flex-col gap-3 border-t border-white/10 pt-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                    >
                        <div class="dashboard-toolbar-settings min-h-[38px] min-w-0 flex-1">
                            <template v-if="selectedWidget">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <template v-if="selectedWidgetNeedsUsername">
                                        <input
                                            v-model="selectedWidget.settings.username"
                                            :class="[DASH_TOOLBAR_FIELD_CLASS, 'min-w-[10rem] flex-1 sm:max-w-md']"
                                            placeholder="Minecraft username"
                                            @input="markDirty"
                                        />
                                    </template>
                                    <select
                                        v-if="selectedWidget.type === 'leaderboard_rank_widget'"
                                        v-model="selectedWidget.settings.sort"
                                        :class="[DASH_TOOLBAR_FIELD_CLASS, 'w-auto min-w-[9rem] shrink-0 cursor-pointer sm:w-44']"
                                        @change="markDirty"
                                    >
                                        <option v-for="opt in LEADERBOARD_SORT_OPTIONS" :key="opt.key" :value="opt.key">{{ opt.label }}</option>
                                    </select>
                                    <select
                                        v-if="selectedWidget.type === 'event_timers_widget'"
                                        v-model="selectedWidget.settings.event_timer_key"
                                        :class="[DASH_TOOLBAR_FIELD_CLASS, 'min-w-0 w-48 shrink-0 cursor-pointer sm:w-56']"
                                        @change="markDirty"
                                    >
                                        <option v-for="ev in TIMER_EVENT_SELECT_OPTIONS" :key="ev.key" :value="ev.key">{{ ev.name }}</option>
                                    </select>
                                    <template
                                        v-if="selectedWidgetNeedsUsername && (selectedWidget.type === 'profile_slayers_widget' || selectedWidget.type === 'profile_skills_widget' || selectedWidget.type === 'profile_collections_widget')"
                                    >
                                        <details
                                            v-if="selectedWidget.type === 'profile_slayers_widget'"
                                            class="dash-toolbar-details shrink-0"
                                        >
                                            <summary :class="DASH_TOOLBAR_FILTER_TRIGGER_CLASS">{{ t('dashboard.slayerFilterSummary') }}</summary>
                                            <div :class="[DASH_TOOLBAR_FILTER_PANEL_CLASS, 'dash-toolbar-panel--slayers']" @click.stop>
                                                <label
                                                    v-for="o in slayerBossOptionsForWidget(selectedWidget)"
                                                    :key="o.key"
                                                    class="dash-toolbar-check"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="slayerKeyChecked(selectedWidget, o.key)"
                                                        @change="toggleSlayerKey(selectedWidget, o.key)"
                                                    />
                                                    <span>{{ o.label }}</span>
                                                </label>
                                            </div>
                                        </details>
                                        <details
                                            v-if="selectedWidget.type === 'profile_skills_widget'"
                                            class="dash-toolbar-details shrink-0"
                                        >
                                            <summary :class="DASH_TOOLBAR_FILTER_TRIGGER_CLASS">{{ t('dashboard.skillFilterSummary') }}</summary>
                                            <div :class="[DASH_TOOLBAR_FILTER_PANEL_CLASS, 'dash-toolbar-panel--skills']" @click.stop>
                                                <label
                                                    v-for="o in skillOptionsForWidget(selectedWidget)"
                                                    :key="o.key"
                                                    class="dash-toolbar-check"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="skillKeyChecked(selectedWidget, o.key)"
                                                        @change="toggleSkillKey(selectedWidget, o.key)"
                                                    />
                                                    <span>{{ o.label }}</span>
                                                </label>
                                            </div>
                                        </details>
                                        <details
                                            v-if="selectedWidget.type === 'profile_collections_widget'"
                                            class="dash-toolbar-details shrink-0"
                                        >
                                            <summary :class="DASH_TOOLBAR_FILTER_TRIGGER_CLASS">{{ t('dashboard.collectionsFilterSummary') }}</summary>
                                            <div :class="[DASH_TOOLBAR_FILTER_PANEL_CLASS, 'dash-toolbar-panel--collections']" @click.stop>
                                                <div class="dash-toolbar-panel-actions">
                                                    <button type="button" class="dash-toolbar-clear" @click="clearCollectionSelection(selectedWidget)">
                                                        {{ t('dashboard.collectionsClear') }}
                                                    </button>
                                                </div>
                                                <div
                                                    v-for="g in collectionPickerGroupsForWidget(selectedWidget)"
                                                    :key="g.id"
                                                    class="dash-toolbar-collection-group"
                                                >
                                                    <div class="dash-toolbar-collection-cat">{{ g.name }}</div>
                                                    <label v-for="it in g.items" :key="it.key" class="dash-toolbar-check dash-toolbar-check--dense">
                                                        <input
                                                            type="checkbox"
                                                            :checked="collectionKeyChecked(selectedWidget, it.key)"
                                                            @change="toggleCollectionKey(selectedWidget, it.key)"
                                                        />
                                                        <span>{{ it.label }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </details>
                                    </template>
                                </div>
                            </template>
                            <p v-else class="text-[11px] leading-snug text-white/45">{{ t('dashboard.toolbarSelectWidgetHint') }}</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:justify-end">
                            <button
                                type="button"
                                class="dash-btn shrink-0"
                                :class="isPublic ? 'dash-btn--visibility-public' : 'dash-btn--visibility-private'"
                                @click="toggleDashboardVisibility"
                            >
                                {{ isPublic ? t('dashboard.publicDashboard') : t('dashboard.privateDashboard') }}
                            </button>
                            <button type="button" class="dash-btn dash-btn--muted shrink-0" :disabled="layoutUndoStack.length === 0" @click="undoLayoutChange">
                                {{ t('dashboard.undo') }}
                            </button>
                            <button type="button" class="dash-btn dash-btn--accent shrink-0" @click="openAddWidgetsModal">{{ t('dashboard.addWidgets') }}</button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-45"
                                :class="canOpenEdit ? 'border border-profit/35 bg-profit/20 text-profit hover:bg-profit/30 hover:text-white' : 'border border-border/60 bg-surface-800/50 text-neutral'"
                                :disabled="!canOpenEdit"
                                @click="toggleEditMode"
                            >
                                {{ t('dashboard.done') }}
                            </button>
                            <span v-if="isSaving" class="w-full text-right text-xs text-neutral sm:w-auto sm:text-left">{{ t('dashboard.saving') }}</span>
                        </div>
                    </div>

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
                                @pointerdown="onWidgetPointerDown(widget, $event)"
                            >
                                <button
                                    v-if="editMode"
                                    type="button"
                                    class="widget-delete-btn"
                                    :aria-label="t('dashboard.removeWidget')"
                                    @click.stop="removeWidget(widget.clientId)"
                                >
                                    ×
                                </button>

                                <div class="dashboard-widget__content">
                                    <template v-if="widget.type === 'skin_view_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-skin-preview">
                                            <PlayerModel :uuid="widgetProfileData(widget).uuid" :width="skinModelSize(widget).width" :height="skinModelSize(widget).height" :zoom="0.72" />
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'inventory_gui_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-inventory-panel">
                                            <InventoryGrid
                                                :items="widgetProfileData(widget).currentData.inventory ?? []"
                                                :show-hotbar="true"
                                                :style="inventoryGridStyle(widget)"
                                            />
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_skills_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll">
                                            <div v-if="skillRowsForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <div v-for="skill in skillRowsForWidget(widget)" :key="skill.name" class="dash-skill-row dash-skill-row--rich">
                                                <div class="dash-skill-label">
                                                    <div v-if="skillIconItem(skill.name)" class="dash-skill-icon-slot">
                                                        <ItemSlot :item="skillIconItem(skill.name)" />
                                                    </div>
                                                    <span class="dash-skill-name">{{ capitalize(skill.name) }}</span>
                                                    <span class="dash-skill-lvl" :class="{ 'dash-skill-lvl--maxed': skill.level >= skill.maxLevel }">{{ Math.min(skill.level, skill.maxLevel) }}</span>
                                                </div>
                                                <div class="dash-skill-track">
                                                    <div
                                                        class="dash-skill-fill"
                                                        :class="{ 'dash-skill-fill--maxed': skill.level >= skill.maxLevel }"
                                                        :style="{ width: (skill.level >= skill.maxLevel ? 100 : Math.min(100, (skill.progress ?? 0) * 100)) + '%' }"
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_slayers_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll">
                                            <div v-if="slayerRowsForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <div v-for="row in slayerRowsForWidget(widget)" :key="row.key" class="dash-slayer-row dash-slayer-row--rich">
                                                <div class="dash-slayer-head">
                                                    <div class="dash-slayer-icon-slot">
                                                        <ItemSlot :item="slayerIconItem(row.key)" />
                                                    </div>
                                                    <span class="dash-slayer-name">{{ row.name }}</span>
                                                    <span class="dash-slayer-lvl">Lv {{ row.level?.currentLevel ?? 0 }}</span>
                                                </div>
                                                <div class="dash-skill-track dash-skill-track--slayer">
                                                    <div
                                                        class="dash-skill-fill dash-skill-fill--slayer"
                                                        :class="{ 'dash-skill-fill--maxed': (row.level?.currentLevel ?? 0) >= (row.level?.maxLevel ?? 9) }"
                                                        :style="{ width: (Math.min(1, row.level?.progress ?? 0) * 100) + '%' }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <p v-if="widgetCurrentData(widget)?.slayers?.total_slayer_xp != null" class="dash-slayer-total">
                                                {{ t('profileStats.totalSlayerXP') }}
                                                <strong>{{ Number(widgetCurrentData(widget).slayers.total_slayer_xp).toLocaleString() }}</strong>
                                            </p>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_collections_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll">
                                            <div v-if="collectionRowsForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">
                                                {{ t('dashboard.collectionsPickHint') }}
                                            </div>
                                            <div
                                                v-for="row in collectionRowsForWidget(widget)"
                                                :key="row.categoryId + ':' + row.id"
                                                class="dash-collection-row"
                                            >
                                                <div class="dash-collection-head">
                                                    <div class="dash-collection-icon">
                                                        <ItemSlot
                                                            :item="{ skyblock_id: row.id, name: row.name, rarity: row.maxed ? 'legendary' : 'uncommon' }"
                                                        />
                                                    </div>
                                                    <div class="dash-collection-meta">
                                                        <span class="dash-collection-name">{{ row.name }}</span>
                                                        <span class="dash-collection-sub">{{ row.categoryName }}</span>
                                                    </div>
                                                    <span
                                                        class="dash-collection-tier"
                                                        :class="{ 'dash-collection-tier--maxed': row.maxed }"
                                                    >{{ romanCollectionTier(row.tier) }}</span>
                                                </div>
                                                <div class="dash-skill-track dash-skill-track--collection">
                                                    <div
                                                        class="dash-skill-fill dash-skill-fill--collection"
                                                        :class="{ 'dash-skill-fill--maxed': row.maxed }"
                                                        :style="{ width: Math.round(Math.min(1, row.progress ?? 0) * 100) + '%' }"
                                                    ></div>
                                                </div>
                                                <div class="dash-collection-amt">
                                                    {{ Number(row.amount ?? 0).toLocaleString() }}
                                                    <template v-if="row.nextTierAmount != null && !row.maxed">
                                                        <span class="dash-collection-amt-sep">/</span>
                                                        {{ Number(row.nextTierAmount).toLocaleString() }}
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_networth_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-networth-block widget-networth-block--rich">
                                            <div v-if="!widgetCurrentData(widget)?.networth" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <template v-else>
                                                <div class="dash-nw-main">{{ formatDashboardCoins(widgetCurrentData(widget).networth.networth) }}</div>
                                                <div class="dash-nw-sub">{{ t('profileStats.networth') }}</div>
                                                <div class="dash-nw-rows">
                                                    <div class="dash-nw-row">
                                                        <span class="dash-nw-k">{{ t('profileStats.purse') }}</span>
                                                        <span class="dash-nw-v">{{ formatDashboardCoins(widgetCurrentData(widget).networth.purse) }}</span>
                                                    </div>
                                                    <div class="dash-nw-row">
                                                        <span class="dash-nw-k">{{ t('profileStats.bank') }}</span>
                                                        <span class="dash-nw-v">{{ formatDashboardCoins(widgetCurrentData(widget).networth.bank) }}</span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_pets_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll">
                                            <div v-if="petsPreviewList(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('profileStats.noPetData') }}</div>
                                            <div v-else class="dash-pets-grid">
                                                <div v-for="(pet, pi) in petsPreviewList(widget)" :key="pi" class="dash-pet-cell">
                                                    <div class="dash-pet-slot">
                                                        <ItemSlot :item="pet" />
                                                    </div>
                                                    <span class="dash-pet-lvl" :style="{ color: petTierColors[pet.tier] || '#aaa' }">Lv {{ pet.level?.level ?? '?' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_equipment_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll widget-gear-vertical">
                                            <div v-if="equipmentSlotsForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <div v-for="(item, ei) in equipmentSlotsForWidget(widget)" :key="'eq-' + ei" class="dash-gear-row">
                                                <div class="dash-gear-slot">
                                                    <ItemSlot :item="item" />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_armor_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll widget-gear-vertical">
                                            <div v-if="armorSlotsForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <div v-for="(item, ai) in armorSlotsForWidget(widget)" :key="'ar-' + ai" class="dash-gear-row">
                                                <div class="dash-gear-slot">
                                                    <ItemSlot :item="item" />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'profile_weapons_widget'">
                                        <div v-if="widgetStatusText(widget)" :class="widgetStatusClass(widget)">{{ widgetStatusText(widget) }}</div>
                                        <div v-else-if="widgetProfileData(widget)" class="widget-profile-scroll dash-weapons-scroll">
                                            <div v-if="weaponsListForWidget(widget).length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            <div v-else class="dash-weapons-strip">
                                                <div v-for="(item, wi) in weaponsListForWidget(widget)" :key="'wp-' + wi" class="dash-weapon-icon">
                                                    <ItemSlot :item="item" />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="widget-state-copy">{{ t('dashboard.noProfileData') }}</div>
                                    </template>

                                    <template v-else-if="widget.type === 'event_timers_widget'">
                                        <div class="widget-profile-scroll dash-event-timer-widget">
                                            <div v-if="dashboardSnapshotLoading && !dashboardSnapshot" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.loading') }}</div>
                                            <template v-else-if="dashboardSnapshot">
                                                <template v-if="widgetEventTimerCard(widget)">
                                                    <div
                                                        class="dash-event-single"
                                                        :class="{
                                                            'dash-event-single--boosted': widgetEventTimerCard(widget).boosted,
                                                            'dash-event-single--live': widgetEventTimerCard(widget).isActive,
                                                        }"
                                                    >
                                                        <div class="dash-event-single__ring">
                                                            <svg viewBox="0 0 128 128" class="dash-event-single__svg -rotate-90">
                                                                <circle cx="64" cy="64" r="36" stroke="rgba(148, 163, 184, 0.22)" stroke-width="10" fill="none" />
                                                                <circle
                                                                    cx="64"
                                                                    cy="64"
                                                                    r="36"
                                                                    stroke-width="10"
                                                                    fill="none"
                                                                    stroke-linecap="round"
                                                                    :stroke="widgetEventTimerCard(widget).isActive ? '#22c55e' : '#f59e0b'"
                                                                    :stroke-dasharray="2 * Math.PI * 36"
                                                                    :stroke-dashoffset="eventRingStrokeOffset(widgetEventTimerCard(widget).progress ?? 0)"
                                                                />
                                                            </svg>
                                                            <div class="dash-event-single__time">{{ formatDurationSeconds(widgetEventTimerCard(widget).stateRemaining) }}</div>
                                                        </div>
                                                        <div class="dash-event-single__body">
                                                            <div class="dash-event-single__top">
                                                                <h3 class="dash-event-single__name">{{ widgetEventTimerCard(widget).name }}</h3>
                                                                <span
                                                                    class="dash-event-single__pill"
                                                                    :class="widgetEventTimerCard(widget).isActive ? 'dash-event-single__pill--live' : 'dash-event-single__pill--soon'"
                                                                >
                                                                    {{ widgetEventTimerCard(widget).isActive ? t('eventTimer.live') : t('eventTimer.upcoming') }}
                                                                </span>
                                                            </div>
                                                            <p class="dash-event-single__phase">
                                                                {{ widgetEventTimerCard(widget).isActive ? widgetEventTimerCard(widget).activeLabel : widgetEventTimerCard(widget).upcomingLabel }}
                                                            </p>
                                                            <div class="dash-skill-track dash-skill-track--event dash-event-single__bar">
                                                                <div
                                                                    class="dash-skill-fill dash-skill-fill--event"
                                                                    :class="{ 'dash-skill-fill--maxed': widgetEventTimerCard(widget).isActive }"
                                                                    :style="{ width: Math.round((widgetEventTimerCard(widget).progress ?? 0) * 100) + '%' }"
                                                                ></div>
                                                            </div>
                                                            <div class="dash-event-single__next">
                                                                {{ t('eventTimer.next') }}
                                                                <span class="dash-event-single__clock">{{ formatEventClock(widgetEventTimerCard(widget).nextStart) }}</span>
                                                            </div>
                                                            <div v-if="widgetEventTimerCard(widget).boosted" class="dash-event-single__boost">{{ t('eventTimer.specialPerk') }}</div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div v-else class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.eventTimerUnavailable') }}</div>
                                            </template>
                                            <div v-else class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                        </div>
                                    </template>

                                    <template v-else-if="widget.type === 'skyblock_calendar_widget'">
                                        <div class="widget-profile-scroll">
                                            <div v-if="dashboardSnapshotLoading && !dashboardSnapshot" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.loading') }}</div>
                                            <template v-else-if="dashboardCalendar">
                                                <div class="dash-cal-head">
                                                    <div class="dash-cal-title">{{ dashboardCalendar.monthName }} {{ dashboardCalendar.day }}, Year {{ dashboardCalendar.year }}</div>
                                                    <div class="dash-cal-sub">{{ dashboardCalendar.season }} · SB {{ String(dashboardCalendar.hour24).padStart(2, '0') }}:{{ String(dashboardCalendar.minute).padStart(2, '0') }}</div>
                                                </div>
                                                <ul class="dash-cal-list">
                                                    <li v-for="(ev, ci) in dashboardCalendar.events.slice(0, 10)" :key="ci + ev.key" class="dash-cal-li">{{ ev.name }}</li>
                                                </ul>
                                            </template>
                                            <div v-else class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                        </div>
                                    </template>

                                    <template v-else-if="widget.type === 'mayor_status_widget'">
                                        <div class="widget-profile-scroll">
                                            <div v-if="dashboardSnapshotLoading && !dashboardSnapshot" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.loading') }}</div>
                                            <template v-else-if="dashboardSnapshot?.mayor">
                                                <div class="dash-mayor-head">
                                                    <img
                                                        v-if="dashboardSnapshot.mayor.uuid"
                                                        :src="getHeadUrl(dashboardSnapshot.mayor.uuid, 48)"
                                                        alt=""
                                                        class="dash-mayor-headimg"
                                                    />
                                                    <div>
                                                        <div class="dash-mayor-name">{{ dashboardSnapshot.mayor.name }}</div>
                                                        <div class="dash-mayor-sub">{{ t('dashboard.currentMayor') }}</div>
                                                    </div>
                                                </div>
                                                <ul class="dash-mayor-perks">
                                                    <li v-for="(p, pi) in (dashboardSnapshot.mayor.perks || []).slice(0, 3)" :key="pi" class="dash-mayor-perk">{{ p.name }}</li>
                                                </ul>
                                            </template>
                                            <div v-else class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                        </div>
                                    </template>

                                    <template v-else-if="widget.type === 'bazaar_top_widget'">
                                        <div class="widget-profile-scroll">
                                            <div v-if="bazaarTopLoading && bazaarTopRows.length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.loading') }}</div>
                                            <template v-else>
                                                <div v-for="(row, bi) in bazaarTopRows" :key="row.product_id + '-' + bi" class="dash-bazaar-row">
                                                    <div class="dash-bazaar-icon">
                                                        <ItemSlot :item="{ skyblock_id: row.product_id, name: row.name, rarity: 'common' }" />
                                                    </div>
                                                    <div class="dash-bazaar-meta">
                                                        <div class="dash-bazaar-name">{{ row.name }}</div>
                                                        <div class="dash-bazaar-stat">{{ t('dashboard.bazaarMargin') }} {{ Number(row.margin ?? 0).toFixed(1) }}</div>
                                                    </div>
                                                </div>
                                                <div v-if="!bazaarTopLoading && bazaarTopRows.length === 0" class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.widgetEmptySection') }}</div>
                                            </template>
                                        </div>
                                    </template>

                                    <template v-else-if="widget.type === 'leaderboard_rank_widget'">
                                        <div
                                            class="widget-networth-block widget-networth-block--rich"
                                            :class="{ 'dash-lb--compact': Number(widget.w) <= 1 }"
                                        >
                                            <template v-if="(widget.settings?.username ?? '').trim().length < 2">
                                                <div class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.leaderboardNeedName') }}</div>
                                            </template>
                                            <template v-else>
                                                <template v-if="leaderboardLookupByKey[leaderboardLookupKey(widget)]?.rank">
                                                    <div class="dash-lb-rank">#{{ leaderboardLookupByKey[leaderboardLookupKey(widget)].rank }}</div>
                                                    <div class="dash-lb-name">{{ leaderboardLookupByKey[leaderboardLookupKey(widget)].name }}</div>
                                                    <div class="dash-lb-sort">{{ leaderboardSortLabel(widget) }}</div>
                                                </template>
                                                <div v-else class="widget-state-copy widget-state-copy--muted">{{ t('dashboard.leaderboardNotFound') }}</div>
                                            </template>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="widget-state-copy">{{ widget.title }}</div>
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
                </div>
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

                                <template v-else-if="template.preview === 'skills'">
                                    <div class="mini-skills-preview">
                                        <span v-for="s in 6" :key="s" class="mini-skill-bar"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'slayers'">
                                    <div class="mini-slayers-preview mini-slayers-preview--tiles">
                                        <span v-for="s in 6" :key="s" class="mini-slayer-cell"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'collections'">
                                    <div class="mini-collections-preview">
                                        <span v-for="c in 6" :key="c" class="mini-collection-cell"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'networth'">
                                    <div class="mini-nw-preview">
                                        <span class="mini-nw-coins">⌂</span>
                                        <span class="mini-nw-line"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'pets'">
                                    <div class="mini-pets-preview">
                                        <span v-for="p in 6" :key="p" class="mini-pet-dot"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'equipment'">
                                    <div class="mini-equip-preview mini-equip-preview--vertical">
                                        <span v-for="e in 4" :key="e" class="mini-equip-cell"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'armor'">
                                    <div class="mini-equip-preview mini-equip-preview--vertical">
                                        <span v-for="e in 4" :key="e" class="mini-equip-cell"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'weapons'">
                                    <div class="mini-weapons-preview">
                                        <span v-for="w in 5" :key="w" class="mini-weapon-cell"></span>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'events'">
                                    <div class="mini-event-single-preview">
                                        <span class="mini-event-ring"></span>
                                        <div class="mini-event-single-lines">
                                            <span class="mini-event-line wide"></span>
                                            <span class="mini-event-line"></span>
                                        </div>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'calendar'">
                                    <div class="mini-cal-preview">
                                        <div class="mini-cal-head"></div>
                                        <div class="mini-cal-lines">
                                            <span v-for="c in 4" :key="c" class="mini-cal-line"></span>
                                        </div>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'mayor'">
                                    <div class="mini-mayor-preview">
                                        <span class="mini-mayor-avatar"></span>
                                        <div class="mini-mayor-lines">
                                            <span class="mini-mayor-line"></span>
                                            <span class="mini-mayor-line short"></span>
                                        </div>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'bazaar'">
                                    <div class="mini-bazaar-preview">
                                        <div v-for="b in 4" :key="b" class="mini-bazaar-row">
                                            <span class="mini-bazaar-dot"></span>
                                            <span class="mini-bazaar-line"></span>
                                        </div>
                                    </div>
                                </template>

                                <template v-else-if="template.preview === 'leaderboard'">
                                    <div class="mini-lb-preview">#</div>
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
.dash-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    padding: 0.5rem 0.875rem;
    font-size: 0.75rem;
    font-weight: 600;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        transform 0.15s ease,
        opacity 0.15s ease;
}

.dash-btn:disabled {
    cursor: not-allowed;
    opacity: 0.4;
    transform: none;
}

.dash-btn--muted:not(:disabled):hover {
    border-color: #404048;
    background-color: rgba(48, 48, 52, 0.95);
}

.dash-btn--muted {
    border: 1px solid rgba(48, 48, 48, 0.85);
    background: rgba(26, 26, 32, 0.85);
    color: rgba(255, 255, 255, 0.9);
}

.dash-btn--accent:not(:disabled):hover {
    border-color: rgba(85, 255, 85, 0.45);
    background: rgba(85, 255, 85, 0.22);
}

.dash-btn--accent {
    border: 1px solid rgba(85, 255, 85, 0.32);
    background: rgba(85, 255, 85, 0.14);
    color: #55ff55;
}

.dash-btn--visibility-public:not(:disabled):hover {
    border-color: rgba(85, 255, 85, 0.5);
    background: rgba(85, 255, 85, 0.22);
}

.dash-btn--visibility-public {
    border: 1px solid rgba(85, 255, 85, 0.38);
    background: rgba(85, 255, 85, 0.12);
    color: #b7fbd6;
}

.dash-btn--visibility-private:not(:disabled):hover {
    border-color: rgba(245, 158, 11, 0.45);
    background: rgba(245, 158, 11, 0.16);
}

.dash-btn--visibility-private {
    border: 1px solid rgba(245, 158, 11, 0.38);
    background: rgba(245, 158, 11, 0.1);
    color: #fde68a;
}

.dashboard-toolbar-settings {
    position: relative;
    z-index: 20;
}

.dash-toolbar-details {
    position: relative;
    z-index: 30;
}

.dash-toolbar-summary::-webkit-details-marker {
    display: none;
}

.dash-toolbar-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    z-index: 120;
    min-width: 220px;
    max-width: min(340px, 92vw);
    max-height: 280px;
    overflow-y: auto;
}

.dash-toolbar-check {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
    padding: 6px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.dash-toolbar-check:last-child {
    margin-bottom: 0;
}

.dash-toolbar-check:hover {
    background: rgba(255, 255, 255, 0.05);
}

.dash-toolbar-check--dense {
    margin-bottom: 2px;
    padding: 4px 6px;
    font-size: 11px;
}

.dash-toolbar-check input[type='checkbox'] {
    appearance: none;
    -webkit-appearance: none;
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    margin: 0;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(0, 0, 0, 0.22);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}

.dash-toolbar-check input[type='checkbox']:hover {
    border-color: rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.06);
}

.dash-toolbar-check input[type='checkbox']:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px rgba(85, 255, 85, 0.28);
}

.dash-toolbar-check input[type='checkbox']:checked {
    border-color: rgba(85, 255, 85, 0.55);
    background-color: rgba(85, 255, 85, 0.22);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M3.5 8.2 6.4 11l6.1-6.1' stroke='%23bbf7d0' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-size: 12px 12px;
    background-position: center;
    background-repeat: no-repeat;
}

.dash-toolbar-check input[type='checkbox']:checked:hover {
    border-color: rgba(85, 255, 85, 0.75);
    background-color: rgba(85, 255, 85, 0.3);
}

.dash-toolbar-panel-actions {
    margin-bottom: 10px;
}

.dash-toolbar-clear {
    font-size: 10px;
    font-weight: 600;
    color: rgba(251, 191, 36, 0.95);
    text-decoration: underline;
    text-underline-offset: 2px;
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
}

.dash-toolbar-collection-group {
    margin-bottom: 10px;
}

.dash-toolbar-collection-cat {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.38);
    margin-bottom: 4px;
}

.dash-collection-row {
    margin-bottom: 10px;
}

.dash-collection-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.dash-collection-icon {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
}

.dash-collection-icon :deep(.piece) {
    width: 32px;
    height: 32px;
}

.dash-collection-meta {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.dash-collection-name {
    font-size: 11px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-collection-sub {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.42);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-collection-tier {
    font-size: 11px;
    font-weight: 800;
    color: rgba(200, 200, 210, 0.85);
    flex-shrink: 0;
}

.dash-collection-tier--maxed {
    color: #fbbf24;
}

.dash-skill-track--collection {
    height: 5px;
}

.dash-skill-fill--collection {
    background: linear-gradient(90deg, rgba(120, 200, 255, 0.75), rgba(180, 120, 255, 0.55));
}

.dash-collection-amt {
    margin-top: 3px;
    font-size: 9px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.55);
    font-variant-numeric: tabular-nums;
}

.dash-collection-amt-sep {
    margin: 0 3px;
    opacity: 0.45;
}

.mini-collections-preview {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    padding: 22px 36px;
}

.mini-collection-cell {
    aspect-ratio: 1;
    border-radius: 6px;
    border: 1px solid rgba(120, 200, 255, 0.28);
    background: rgba(26, 26, 32, 0.85);
}

.dashboard-canvas {
    position: relative;
    display: grid;
    width: 100%;
    min-height: 720px;
    gap: 10px;
    padding: 4px 0;
    border-radius: 0;
    border: none;
    background: transparent;
    overflow: hidden;
}

.dashboard-grid-overlay {
    position: absolute;
    inset: 4px 0;
    display: grid;
    gap: 10px;
    pointer-events: none;
    z-index: 0;
}

.dashboard-grid-overlay span {
    border: 1px dashed rgba(48, 48, 48, 0.9);
    background: rgba(16, 16, 16, 0.35);
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
    border-radius: 0.5rem;
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
    border-color: rgba(85, 255, 85, 0.55);
}

.dashboard-widget--editing {
    border-color: rgba(85, 255, 85, 0.35);
    cursor: grab;
}

.dashboard-widget--pulse {
    animation: widgetPulse 240ms ease-out;
}

.dashboard-widget--dragging {
    transition: none;
    cursor: grabbing;
    filter: drop-shadow(0 16px 24px rgba(0, 0, 0, 0.32));
}

.widget-delete-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 12;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    border: 1px solid rgba(251, 113, 133, 0.45);
    background: rgba(24, 10, 14, 0.88);
    color: #fecdd3;
    font-size: 16px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition:
        background-color 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease,
        transform 0.15s ease;
}

.widget-delete-btn:hover {
    border-color: rgba(254, 205, 211, 0.65);
    background: rgba(190, 18, 60, 0.55);
    color: #fff;
    transform: scale(1.04);
}

.dashboard-widget__content {
    min-height: 0;
    flex: 1;
    overflow: hidden;
    border-radius: 10px;
    background: transparent;
}

.dashboard-widget__settings {
    margin-top: 12px;
    border-top: 1px solid rgba(48, 48, 48, 0.85);
    padding-top: 10px;
    border-radius: 12px;
    background: transparent;
    padding: 10px;
    backdrop-filter: none;
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

.widget-profile-scroll {
    width: 100%;
    height: 100%;
    min-height: 0;
    overflow: auto;
    padding: 6px 8px 8px;
    box-sizing: border-box;
}

.widget-state-copy--muted {
    color: rgba(255, 255, 255, 0.45);
    font-size: 11px;
}

.dash-skill-row {
    margin-bottom: 7px;
}

.dash-skill-row--rich {
    margin-bottom: 9px;
}

.dash-skill-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    margin-bottom: 4px;
}

.dash-skill-icon-slot {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
}

.dash-skill-icon-slot :deep(.piece) {
    width: 22px;
    height: 22px;
}

.dash-skill-name {
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-skill-lvl {
    font-weight: 700;
    color: #55ff55;
    flex-shrink: 0;
}

.dash-skill-lvl--maxed {
    color: #ffcc66;
}

.dash-skill-track {
    height: 5px;
    border-radius: 4px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-sizing: border-box;
    overflow: hidden;
    position: relative;
}

.dash-skill-track--slayer {
    height: 6px;
}

.dash-skill-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, rgba(85, 170, 255, 0.85), rgba(85, 255, 85, 0.75));
    min-width: 0;
    transition: width 0.2s ease;
}

.dash-skill-fill--slayer {
    background: linear-gradient(90deg, rgba(170, 85, 255, 0.9), rgba(255, 85, 170, 0.75));
}

.dash-skill-fill--maxed {
    background: linear-gradient(90deg, rgba(255, 200, 100, 0.95), rgba(255, 220, 120, 0.8));
}

.dash-slayer-row {
    margin-bottom: 10px;
}

.dash-slayer-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 4px;
}

.dash-slayer-icon-slot {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
}

.dash-slayer-icon-slot :deep(.piece) {
    width: 24px;
    height: 24px;
}

.dash-slayer-name {
    flex: 1;
    min-width: 0;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-slayer-lvl {
    flex-shrink: 0;
    color: #c9a6ff;
    font-weight: 700;
}

.dash-slayer-total {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(48, 48, 48, 0.75);
    font-size: 10px;
    color: rgba(255, 255, 255, 0.55);
}

.dash-slayer-total strong {
    color: rgba(255, 255, 255, 0.88);
    margin-left: 4px;
}

.widget-networth-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 0;
    padding: 8px 10px;
    text-align: center;
}

.dash-nw-main {
    font-size: clamp(15px, 2.4vw, 22px);
    font-weight: 800;
    color: #ffcc66;
    letter-spacing: 0.02em;
}

.dash-nw-sub {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.45);
    margin-top: 2px;
}

.widget-networth-block--rich {
    align-items: stretch;
    text-align: left;
    padding: 10px 12px;
}

.widget-networth-block--rich .dash-nw-main {
    font-size: clamp(18px, 3.2vw, 30px);
    font-weight: 800;
    color: #ffd88a;
    line-height: 1.1;
}

.widget-networth-block--rich .dash-nw-sub {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
}

.dash-nw-rows {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.dash-nw-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid rgba(48, 48, 52, 0.9);
    font-size: 11px;
}

.dash-nw-row .dash-nw-k {
    display: inline;
    color: rgba(255, 255, 255, 0.55);
}

.dash-nw-row .dash-nw-v {
    display: inline;
    font-weight: 800;
    font-size: 12px;
    color: #f5f5f5;
    margin-top: 0;
}

.dash-pets-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 4px 5px;
    align-items: start;
}

.dash-pet-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 0;
}

.dash-pet-slot {
    width: 100%;
    max-width: 40px;
    transform: scale(0.92);
    transform-origin: top center;
}

.dash-pet-slot :deep(.piece) {
    width: 36px;
    height: 36px;
    max-width: 100%;
}

.dash-pet-lvl {
    font-size: 8px;
    font-weight: 700;
    text-align: center;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.widget-gear-vertical {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
}

.dash-gear-row {
    display: flex;
    justify-content: center;
}

.dash-gear-slot {
    width: 44px;
    height: 44px;
}

.dash-gear-slot :deep(.piece) {
    width: 44px;
    height: 44px;
}

.dash-weapons-scroll {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
}

.dash-weapons-strip {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 4px 2px;
    box-sizing: border-box;
}

.dash-weapon-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}

.dash-weapon-icon :deep(.piece) {
    width: 40px;
    height: 40px;
}

.dash-skill-track--event {
    height: 5px;
}

.dash-skill-fill--event {
    background: linear-gradient(90deg, rgba(90, 180, 255, 0.9), rgba(120, 255, 200, 0.65));
}

.dash-event-timer-widget {
    display: flex;
    align-items: stretch;
    justify-content: center;
}

.dash-event-single {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 10px;
    width: 100%;
    max-width: 100%;
    padding: 8px 8px 10px;
    border-radius: 10px;
    border: 1px solid rgba(60, 60, 68, 0.95);
    background: transparent;
    box-shadow: none;
}

.dash-event-single--live {
    border-color: rgba(34, 197, 94, 0.45);
    box-shadow: none;
}

.dash-event-single--boosted {
    border-color: rgba(6, 182, 212, 0.45);
}

.dash-event-single__ring {
    position: relative;
    width: 72px;
    height: 72px;
    flex-shrink: 0;
}

.dash-event-single__svg {
    width: 72px;
    height: 72px;
    display: block;
}

.dash-event-single__time {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: #f8fafc;
    pointer-events: none;
}

.dash-event-single__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dash-event-single__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 6px;
}

.dash-event-single__name {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.25;
    min-width: 0;
}

.dash-event-single__pill {
    flex-shrink: 0;
    border-radius: 999px;
    padding: 2px 7px;
    font-size: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.dash-event-single__pill--live {
    background: transparent;
    border: 1px solid rgba(34, 197, 94, 0.45);
    color: #bbf7d0;
}

.dash-event-single__pill--soon {
    background: transparent;
    border: 1px solid rgba(245, 158, 11, 0.45);
    color: #fde68a;
}

.dash-event-single__phase {
    margin: 0;
    font-size: 10px;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.78);
    line-height: 1.3;
}

.dash-event-single__bar {
    margin-top: 2px;
}

.dash-event-single__next {
    margin-top: 2px;
    font-size: 9px;
    color: rgba(148, 163, 184, 0.95);
}

.dash-event-single__clock {
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    margin-left: 4px;
}

.dash-event-single__boost {
    font-size: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #a5f3fc;
}

.dash-cal-head {
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(48, 48, 52, 0.9);
}

.dash-cal-title {
    font-size: 12px;
    font-weight: 800;
    color: #fff;
}

.dash-cal-sub {
    margin-top: 3px;
    font-size: 10px;
    color: rgba(255, 255, 255, 0.5);
}

.dash-cal-list {
    margin: 0;
    padding: 0 0 0 14px;
    font-size: 10px;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.45;
}

.dash-cal-li {
    margin-bottom: 2px;
}

.dash-mayor-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.dash-mayor-headimg {
    width: 36px;
    height: 36px;
    border-radius: 7px;
    border: 1px solid rgba(60, 60, 68, 0.95);
    image-rendering: pixelated;
}

.dash-mayor-name {
    font-size: 12px;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
}

.dash-mayor-sub {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.5);
}

.dash-mayor-perks {
    margin: 0;
    padding: 0 0 0 12px;
    font-size: 9px;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.35;
}

.dash-mayor-perk {
    margin-bottom: 2px;
}

.dash-bazaar-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.dash-bazaar-icon {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
}

.dash-bazaar-icon :deep(.piece) {
    width: 28px;
    height: 28px;
}

.dash-bazaar-name {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-bazaar-stat {
    font-size: 9px;
    color: rgba(85, 255, 140, 0.85);
}

.dash-bazaar-meta {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.dash-lb-rank {
    font-size: clamp(22px, 4vw, 34px);
    font-weight: 900;
    color: #ffd88a;
    line-height: 1;
}

.dash-lb-name {
    margin-top: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
}

.dash-lb-sort {
    margin-top: 4px;
    font-size: 10px;
    color: rgba(255, 255, 255, 0.5);
}

.dash-lb--compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 0;
    padding: 4px 2px;
}

.dash-lb--compact .dash-lb-rank {
    font-size: clamp(16px, 5vw, 22px);
}

.dash-lb--compact .dash-lb-name {
    margin-top: 4px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.25;
    word-break: break-word;
    max-width: 100%;
}

.dash-lb--compact .dash-lb-sort {
    margin-top: 3px;
    font-size: 8px;
    line-height: 1.2;
}

.dashboard-drag-ghost {
    border-radius: 10px;
    border: 1px solid rgba(85, 255, 85, 0.35);
    background: rgba(85, 255, 85, 0.08);
    box-shadow: inset 0 0 0 1px rgba(48, 48, 48, 0.5);
    pointer-events: none;
    z-index: 0;
}

.dashboard-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(4, 6, 10, 0.72);
    backdrop-filter: blur(8px);
}

.dashboard-modal {
    width: min(980px, calc(100vw - 28px));
    max-height: min(82vh, 820px);
    overflow: auto;
    border-radius: 1.25rem;
    border: 1px solid rgba(48, 48, 48, 0.85);
    background: rgba(16, 16, 16, 0.96);
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
    border-radius: 1rem;
    border: 1px solid rgba(48, 48, 48, 0.85);
    background: rgba(26, 26, 32, 0.45);
    padding: 14px;
    text-align: left;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        background-color 160ms ease;
}

.template-card:hover {
    transform: translateY(-2px);
    border-color: rgba(64, 64, 72, 0.95);
    background: rgba(26, 26, 32, 0.72);
}

.template-card__preview {
    min-height: 220px;
    border-radius: 0.75rem;
    border: 1px solid rgba(48, 48, 48, 0.75);
    background: rgba(16, 16, 16, 0.55);
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
    border: 1px solid rgba(48, 48, 48, 0.75);
    background: rgba(16, 16, 16, 0.4);
}

.mini-inventory-slot--filled {
    background: linear-gradient(180deg, rgba(85, 255, 85, 0.22), rgba(85, 255, 85, 0.08));
    border-color: rgba(85, 255, 85, 0.35);
}

.mini-skills-preview {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    padding: 16px 24px;
}

.mini-skill-bar {
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(90deg, rgba(85, 170, 255, 0.5), rgba(85, 255, 85, 0.35));
    opacity: 0.85;
}

.mini-slayers-preview--tiles {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding: 24px 40px;
    font-size: 0;
    letter-spacing: 0;
}

.mini-slayer-cell {
    aspect-ratio: 1;
    border-radius: 6px;
    border: 1px solid rgba(170, 120, 255, 0.35);
    background: rgba(26, 26, 32, 0.85);
}

.mini-equip-preview--vertical {
    flex-direction: column;
    align-items: center;
}

.mini-weapons-preview {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 5px;
    width: 100%;
    padding: 18px 24px;
}

.mini-weapon-cell {
    width: 22px;
    height: 22px;
    border-radius: 5px;
    border: 1px solid rgba(80, 80, 88, 0.85);
    background: rgba(22, 22, 28, 0.85);
}

.mini-event-single-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 18px 28px;
}

.mini-event-ring {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 3px solid rgba(148, 163, 184, 0.35);
    border-top-color: rgba(245, 158, 11, 0.75);
    flex-shrink: 0;
}

.mini-event-single-lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.mini-event-line {
    height: 7px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
}

.mini-event-line.wide {
    width: 70%;
}

.mini-cal-preview {
    width: 100%;
    padding: 18px 24px;
}

.mini-cal-head {
    height: 14px;
    width: 55%;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.15);
    margin-bottom: 10px;
}

.mini-cal-lines {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mini-cal-line {
    height: 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.08);
}

.mini-mayor-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 28px;
}

.mini-mayor-avatar {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(48, 48, 52, 0.9);
}

.mini-mayor-lines {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.mini-mayor-line {
    height: 8px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.12);
}

.mini-mayor-line.short {
    width: 60%;
}

.mini-bazaar-preview {
    width: 100%;
    padding: 18px 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mini-bazaar-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mini-bazaar-dot {
    width: 22px;
    height: 22px;
    border-radius: 4px;
    background: rgba(85, 255, 140, 0.2);
    border: 1px solid rgba(85, 255, 140, 0.35);
}

.mini-bazaar-line {
    flex: 1;
    height: 8px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.1);
}

.mini-lb-preview {
    font-size: 48px;
    font-weight: 900;
    color: rgba(255, 200, 120, 0.45);
}

.mini-nw-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.mini-nw-coins {
    font-size: 42px;
    opacity: 0.55;
}

.mini-nw-line {
    width: 72px;
    height: 10px;
    border-radius: 5px;
    background: linear-gradient(90deg, rgba(255, 200, 100, 0.5), rgba(255, 200, 100, 0.2));
}

.mini-pets-preview {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding: 20px 32px;
}

.mini-pet-dot {
    aspect-ratio: 1;
    border-radius: 8px;
    border: 1px solid rgba(85, 255, 85, 0.25);
    background: rgba(26, 26, 32, 0.8);
}

.mini-equip-preview {
    display: flex;
    gap: 6px;
    padding: 20px 16px;
}

.mini-equip-cell {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    border: 1px solid rgba(48, 48, 48, 0.75);
    background: rgba(16, 16, 16, 0.5);
}

.fade-scale-enter-active,
.fade-scale-leave-active {
    transition:
        opacity 160ms ease,
        transform 160ms ease;
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
