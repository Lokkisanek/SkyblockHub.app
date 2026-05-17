import { section, p, table, callout, list } from '../blocks.js';

const tools = [
    { label: 'SkyCrypt', url: 'https://sky.shiiyu.moe/', external: true },
    { label: 'COFL', url: 'https://sky.coflnet.com/', external: true },
    { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/', external: true },
];

export const metaTopics = [
    {
        slug: 'tricks',
        title: 'Tricks & Tips',
        description: 'Useful SkyBlock tips, QoL tricks, and common pitfalls to avoid.',
        lastUpdated: '2026-04-05',
        sections: [
            section('keybinds', 'Keybind Setup', 2, [
                list([
                    'Z — Open SkyBlock Menu',
                    'V — Open Warp menu',
                    'B — Open Backpack',
                ]),
            ]),
            section('commands', 'Navigation Shortcuts', 2, [
                list([
                    '/warp [location] — fast travel',
                    '/sethome + /home — island teleport',
                    '/booster — check booster cookies',
                    '/ah — Auction House',
                ]),
            ]),
            section('resets', 'Time Management Resets', 2, [
                table(
                    ['Reset', 'What Resets'],
                    [
                        ['~20 SkyBlock minutes', 'Experimentation Table, some quests'],
                        ['Daily (midnight EST)', 'Daily quests, NPC shops'],
                        ['Election cycle (~5 days)', 'Mayor changes'],
                        ['Seasonal', 'Jacob\'s Contest, seasonal events'],
                    ],
                ),
                callout('Do Experimentation Table every day', 'Best Enchanting XP per minute — easy to forget.'),
            ]),
            section('mistakes', 'Common Mistakes', 2, [
                table(
                    ['Mistake', 'Fix'],
                    [
                        ['Buying gear without checking reqs', 'Read item requirements first'],
                        ['Ignoring Fairy Souls', 'Collect them — free permanent stats'],
                        ['Switching methods every patch', 'Stick 2–4 weeks minimum'],
                        ['Not using Buy Orders', 'Save 5–15% vs instant buy'],
                        ['Leaving minions full', 'Check daily — full = no income'],
                        ['Buying at hype peak', 'Wait 24–48h after patches'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'mods',
        title: 'Mods & Tools',
        description: 'The safest, most useful mods for Hypixel SkyBlock with install guidance.',
        lastUpdated: '2026-04-05',
        sections: [
            section('why', 'Why Use Mods?', 2, [
                list([
                    'Price checking and recipe lookup',
                    'Dungeon secrets, slayer timers, farming overlays',
                    'QoL: pet swap, warp menus, collection tracking',
                    'Performance patches for older clients',
                ]),
                callout('Only use trusted mods', 'Download from official GitHub releases — never random Discord links.'),
            ]),
            section('version', 'Minecraft Version Note', 2, [
                p('Most SkyBlock players use 1.8.9 with Forge + SkyHanni/NEU. 1.21+ Fabric users often use Firmament as a NEU replacement.'),
            ]),
            section('essential', 'Essential Mods', 2, [
                p('SkyHanni — all-in-one tracker, overlays, QoL. GitHub: github.com/hannibal002/SkyHanni'),
                p('NotEnoughUpdates (NEU) — prices, recipes, storage. GitHub: github.com/NotEnoughUpdates/NotEnoughUpdates'),
                p('Firmament (1.21+) — modern NEU replacement. GitHub: github.com/nea89o/Firmament'),
            ]),
            section('comparison', 'Mod Comparison', 2, [
                table(
                    ['Mod', 'Version', 'Best Feature', 'Required?'],
                    [
                        ['SkyHanni', '1.8.9 + 1.21', 'Tracker + overlays', 'Yes'],
                        ['NEU', '1.8.9', 'Prices, recipes', 'Yes'],
                        ['Firmament', '1.21+', 'Modern NEU replacement', 'If on 1.21+'],
                        ['Patcher', '1.8.9', 'FPS improvements', 'Recommended'],
                        ['Essential', 'Any', 'Cosmetics + chat', 'Optional'],
                    ],
                ),
            ]),
            section('external', 'External Tools', 2, [
                table(
                    ['Tool', 'URL', 'Best For'],
                    [
                        ['SkyCrypt', 'https://sky.shiiyu.moe/', 'Profile stats'],
                        ['COFL', 'https://sky.coflnet.com/', 'Bazaar + AH prices'],
                        ['Hypixel Wiki', 'https://wiki.hypixel.net/', 'Mechanics reference'],
                        ['SkyblockTools.dev', 'https://skyblocktools.dev/', 'Farming calculator'],
                        ['EliteFarmers', 'https://elitefarmers.com/', 'Farming leaderboards'],
                    ],
                ),
            ]),
            section('install', 'Installing Mods', 2, [
                list([
                    'Launcher method: use Prism/MultiMC with Forge or Fabric profile, add mods to mods folder.',
                    'Manual: download JARs from GitHub releases only, match Minecraft version.',
                ]),
                callout('Keep mods updated', 'Update after SkyBlock patches — outdated mods can crash or show wrong data.'),
            ]),
        ],
        usefulLinks: [
            { label: 'SkyHanni', url: 'https://github.com/hannibal002/SkyHanni', external: true },
            { label: 'NEU', url: 'https://github.com/NotEnoughUpdates/NotEnoughUpdates', external: true },
            { label: 'Firmament', url: 'https://github.com/nea89o/Firmament', external: true },
            { label: 'Fandom Wiki', url: 'https://hypixel-skyblock.fandom.com/', external: true },
            { label: 'SkyBlock.tools', url: 'https://skyblock.tools', external: true },
        ],
    },
    {
        slug: 'news',
        title: 'News & Patches',
        description: 'Patch notes, what changed, who it affects.',
        lastUpdated: '2026-04-05',
        dynamicPatches: true,
        sections: [
            section('read-patch', 'How to Read a Patch', 2, [
                list([
                    'Read economy changes first — they affect your money method.',
                    'Check item reworks that touch your current gear.',
                    'Note new requirements for dungeons, slayers, or islands.',
                    'Wait 24–48h before buying "new meta" gear.',
                    'Update mods after playing on the new patch.',
                ], true),
                callout('Economy Rule', 'If a method got nerfed, prices often shift before players adapt — check COFL.'),
            ]),
            section('sources', 'Primary Sources', 2, [
                table(
                    ['Name', 'URL', 'Description'],
                    [
                        ['Hypixel Forums — Patch Notes', 'https://hypixel.net/forums/skyblock-patch-notes.158/', 'Official source'],
                        ['Hypixel SkyBlock Wiki', 'https://wiki.hypixel.net/', 'Usually updated within hours'],
                        ['SkyCrypt', 'https://sky.shiiyu.moe/', 'Track how patches affect your profile'],
                    ],
                ),
            ]),
            section('related', 'Related Guides', 2, [
                p('After a patch: re-check Money Making, Mayors & Events, and Mods guides.'),
            ]),
        ],
        usefulLinks: [
            { label: 'Patch notes forum', url: 'https://hypixel.net/forums/skyblock-patch-notes.158/', external: true },
            { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/', external: true },
        ],
    },
];
