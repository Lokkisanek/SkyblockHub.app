export const STORAGE_PACKS = 'skyblock_texture_packs';
export const STORAGE_PERFORMANCE = 'skyblock_profile_performance';

export const DEFAULT_PACKS = ['FURFSKY_REBORN', 'HYPIXELPLUS'];

export const PACK_CONFIGS = [
    {
        id: 'FURFSKY_REBORN',
        folder: 'FurfSky_Reborn',
        name: 'FurfSky Reborn',
        version: 'v1.9.0',
        author: 'The Reborn Team',
        priority: 250,
    },
    {
        id: 'HYPIXELPLUS',
        folder: 'Hypixel_Plus',
        name: 'Hypixel Plus',
        version: 'v0.23.4',
        author: 'ic22487',
        priority: 125,
    },
];

export function loadPerformanceMode() {
    try {
        return localStorage.getItem(STORAGE_PERFORMANCE) === '1';
    } catch {
        return false;
    }
}

export function savePerformanceMode(enabled) {
    try {
        localStorage.setItem(STORAGE_PERFORMANCE, enabled ? '1' : '0');
    } catch {
        /* ignore */
    }
}

export function loadEnabledPacks() {
    try {
        const stored = localStorage.getItem(STORAGE_PACKS);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (Array.isArray(parsed)) {
                return parsed;
            }
        }
    } catch {
        /* ignore */
    }
    return [...DEFAULT_PACKS];
}

export function saveEnabledPacks(packIds) {
    try {
        localStorage.setItem(STORAGE_PACKS, JSON.stringify(packIds));
    } catch {
        /* ignore */
    }
}
