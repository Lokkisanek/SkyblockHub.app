import { section, p, table, callout, list, links } from '../blocks.js';

const tools = [
    { label: 'SkyCrypt', url: 'https://sky.shiiyu.moe/', external: true },
    { label: 'COFL', url: 'https://sky.coflnet.com/', external: true },
    { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/', external: true },
    { label: 'Fairy Soul search (YouTube)', url: 'https://www.youtube.com/results?search_query=hypixel+skyblock+all+fairy+souls+location', external: true },
];

export const progressionTopics = [
    {
        slug: 'early',
        title: 'Early Game',
        description: 'Structured start: priorities, money making, and must-have resources.',
        lastUpdated: '2026-04-05',
        sections: [
            section('start', 'Where to Start', 2, [
                callout('Open SkyBlock Menu → Your Skills', 'Your Skills tab shows what to level next. Combat and Foraging are the usual first targets.'),
                p('Focus on unlocking islands, collections, and fairy souls before chasing expensive gear.'),
            ]),
            section('steps', 'Essential First Steps', 2, [
                list([
                    'Complete the tutorial and unlock the Gold Mine.',
                    'Collect fairy souls — free permanent stats.',
                    'Set up basic minions on your island.',
                    'Reach Combat 12 and unlock Spider\'s Den.',
                    'Save for your first real weapon upgrade (Aspect of the End or similar).',
                ], true),
            ]),
            section('gear', 'Early Game Gear Targets', 2, [
                table(
                    ['Slot', 'Budget Target', 'Upgrade To'],
                    [
                        ['Weapon', 'Rogue Sword / AOTJ', 'Aspect of the End'],
                        ['Helmet', 'Farmer\'s / Gold', 'Glacite / Strong Dragon piece'],
                        ['Chestplate', 'Lapis / Farm armor', 'Strong Dragon'],
                        ['Leggings', 'Lapis / Farm armor', 'Strong Dragon'],
                        ['Boots', 'Farm / Gold', 'Tarantula Boots path'],
                        ['Pet', 'Common Tiger', 'Rare Tiger at level 100'],
                    ],
                ),
                callout('Don\'t rush to expensive gear', 'AOTD + Strong Dragon beats random expensive pieces. Upgrade one slot at a time.'),
            ]),
            section('coins', 'How to Get Your First Coins', 2, [
                p('Pick one lane and stick with it for a few days.'),
                list([
                    'Farming — wheat, carrot, potato on hub or island. Aim for: 100–300k/hr.',
                    'Mining — Gold Mine → Deep Caverns. Aim for: 100–400k/hr plus Mining XP.',
                    'Combat — spider drops, graveyard zombies. Aim for: 200–500k/hr with drops.',
                ]),
            ]),
            section('sbxp', 'SkyBlock Levels (SB XP)', 2, [
                p('SB XP unlocks profile perks. Early milestones come from collections, fairy souls, and skill levels.'),
                callout('First goal: Level 10', 'Level 10 is a common early checkpoint for bag upgrades and basic progression.'),
            ]),
            section('minions', 'Minions — Your Passive Income', 2, [
                table(
                    ['Tier', 'Passive/hr', 'Setup cost'],
                    [
                        ['T5', 'Low', 'Cheap — good for collections'],
                        ['T9', 'Moderate', 'Mid investment'],
                        ['T11', 'Best', 'Worth it once you have fuel and slots'],
                    ],
                ),
            ]),
            section('skills-priority', 'Skills to Prioritize', 2, [
                table(
                    ['Skill', 'Why', 'How'],
                    [
                        ['Combat', 'Unlocks areas and slayers', 'Mob grinding, dungeons later'],
                        ['Foraging', 'Strength bonus', 'Jungle wood with Ocelot pet'],
                        ['Farming', 'Health at high levels', 'Hub crops, then Garden'],
                        ['Mining', 'Defense + HotM', 'Deep Caverns → Dwarven Mines'],
                    ],
                ),
            ]),
            section('mistakes', 'Common Early Game Mistakes', 2, [
                list([
                    'Buying endgame gear before you can use it.',
                    'Ignoring fairy souls and collection unlocks.',
                    'Spreading coins across too many hobbies.',
                    'Not using Bazaar buy orders.',
                    'Leaving minions full for days.',
                ]),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'mid',
        title: 'Mid Game',
        description: 'You have basic gear and some coins — transition into mid game and what to focus on.',
        lastUpdated: '2026-04-05',
        sections: [
            section('check', 'You\'re in Mid Game If…', 2, [
                list(['Skill Average 20–35', 'Dungeons F4–F5 cleared', '100+ Magic Power', '1–5M coins/hr from one method']),
            ]),
            section('mp', 'The Magic Power Wall', 2, [
                p('Accessories are the biggest stat multiplier in mid game. Duplicate accessories do not stack MP.'),
                table(
                    ['Rarity', 'MP per accessory'],
                    [
                        ['Common', '3'], ['Uncommon', '5'], ['Rare', '8'], ['Epic', '12'],
                        ['Legendary', '16'], ['Mythic', '22'], ['Special', '3'], ['Very Special', '5'],
                    ],
                ),
                callout('MP breakpoints matter', '300 MP is your first major target. 500 MP is the next big jump for damage.'),
            ]),
            section('gear', 'Mid Game Gear Targets', 2, [
                table(
                    ['Slot', 'Early Mid', 'Late Mid'],
                    [
                        ['Weapon', 'Giant\'s Sword / AotD', 'Livid Dagger / Hyperion path'],
                        ['Armor', 'Strong Dragon', 'Shadow Assassin'],
                        ['Pet', 'Lvl 100 Rare Tiger', 'Lvl 100 Leg. Tiger / Enderman'],
                        ['Accessories', '50–100 fillers', 'Full Magical Power set'],
                    ],
                ),
            ]),
            section('dungeons', 'Dungeons Progression', 2, [
                table(
                    ['Floor', 'Catacombs Req.', 'Key Drop'],
                    [
                        ['F1', 'None', 'Basic loot'],
                        ['F2', 'None', 'Bonzo\'s Staff / Scarf gear'],
                        ['F3', 'None', 'Adaptive Armor'],
                        ['F4', 'Cata 12', 'Shadow Assassin'],
                        ['F5', 'Cata 18', 'Livid Dagger'],
                        ['F6', 'Cata 24', 'Necron / Maxor / Storm gear'],
                        ['F7', 'Cata 28', 'Wither Blade / Hyperion'],
                    ],
                ),
                callout('Class levels matter', 'Level all dungeon classes — passives stack across your profile.'),
            ]),
            section('money', 'Money Making in Mid Game', 2, [
                table(
                    ['Method', 'Coins/hr', 'Requirements'],
                    [
                        ['Garden farming', '5–15M', 'Farming Fortune 300+, good hoe'],
                        ['Gemstone mining', '3–8M', 'HOTM 7+, Drill, Crystal Hollows'],
                        ['Dungeons (F5–F6)', '3–7M', 'Cata 18–24, decent team'],
                        ['Enderman Slayer T3', '2–5M', 'Cheap enough to run profitably'],
                        ['Lava Fishing', '1–3M', 'Fishing 30+, lava rod'],
                    ],
                ),
            ]),
            section('milestones', 'Key Milestones to Hit', 2, [
                list([
                    'Unlock Dwarven Mines (give Rhys 15 enchanted ores)',
                    'Reach Heart of the Mountain Level 5',
                    'Clear Dungeons Floor 5 for the first time',
                    'Reach Slayer Level 5 in at least one type',
                    'Hit 300 Magic Power',
                    'Unlock your Garden (Farming Islands quest)',
                    'Get a Legendary pet at Level 100',
                ]),
            ]),
        ],
        usefulLinks: tools.slice(0, 3),
    },
    {
        slug: 'end',
        title: 'End Game',
        description: 'What endgame looks like and the gear targets to work toward.',
        lastUpdated: '2026-04-05',
        sections: [
            section('check', 'You\'re in End Game If…', 2, [
                list(['F7 / M1+ cleared', '500+ Magic Power', '10M+/hr from one method', 'Legendary pet level 100']),
            ]),
            section('weapons', 'Endgame Weapons', 2, [
                table(
                    ['Weapon', 'Use Case'],
                    [
                        ['Hyperion', 'Best Mage weapon (F7/M mode)'],
                        ['Juju Shortbow', 'Best Archer weapon (M1–M5)'],
                        ['Midas Staff', 'Late Mage with high investment'],
                        ['Astraea', 'Archer endgame'],
                        ['Terminator', 'Top-tier Archer (very expensive)'],
                        ['Shadow Fury', 'Berserk endgame'],
                    ],
                ),
            ]),
            section('armor', 'Armor Sets', 2, [
                table(
                    ['Set', 'Use Case'],
                    [
                        ['Necron\'s Armor', 'Best general F7 / M1–M3'],
                        ['Storm\'s Armor', 'Budget endgame Mage'],
                        ['Goldor\'s Armor', 'Tank / carry slot'],
                        ['Maxor\'s Boots', 'Slot in with other sets'],
                        ['Crimson Armor', 'Best Kuudra / Berserk'],
                        ['Molten Armor', 'Best Kuudra budget'],
                    ],
                ),
                callout('Reforges matter more here', 'A good reforge on endgame gear often beats a raw upgrade.'),
            ]),
            section('master', 'Master Mode Dungeons', 2, [
                table(
                    ['Floor', 'Cata Req.', 'Notable Drops'],
                    [
                        ['M1', '30', 'Recombobulator 3000, good coins'],
                        ['M2', '33', 'Recombobulator, T12 books'],
                        ['M3', '35', 'Master Skull T4, coins'],
                        ['M4', '37', 'Master Skull T5, Obsidian chest'],
                        ['M5', '38', 'Starred gear, coins/hr peaks'],
                        ['M6', '40', 'High-value starred items'],
                        ['M7', '43', 'Wither Essence farming'],
                    ],
                ),
            ]),
            section('kuudra-summary', 'Kuudra Tiers', 2, [
                table(
                    ['Tier', 'Difficulty', 'Reward Focus'],
                    [
                        ['T1 Basic', 'Easy', 'Learning the mechanic'],
                        ['T2 Hot', 'Moderate', 'Good introduction'],
                        ['T3 Burning', 'Hard', 'Profit starts here'],
                        ['T4 Fiery', 'Very Hard', 'Best coins/hr with setup'],
                        ['T5 Infernal', 'Extreme', 'Best overall rewards'],
                    ],
                ),
            ]),
            section('mp-targets', 'Magic Power — Endgame Targets', 2, [
                table(
                    ['MP Range', 'Status'],
                    [
                        ['500', 'Mid-game standard, functional'],
                        ['600', 'Good endgame baseline'],
                        ['700', 'Strong — common target'],
                        ['800', 'Excellent — significant investment'],
                        ['900+', 'Min-maxed — diminishing returns'],
                    ],
                ),
            ]),
            section('skills', 'Skill Targets', 2, [
                table(
                    ['Skill', 'Target', 'Reason'],
                    [
                        ['Farming', '60', '+242 Health'],
                        ['Mining', '60', 'Defense + endgame content'],
                        ['Combat', '60', 'Strength scaling'],
                        ['Dungeon', '40+', 'Required for M5+'],
                        ['Enchanting', '60', 'Int scaling'],
                        ['Fishing', '50', 'Health'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools.slice(0, 3),
    },
    {
        slug: 'skills',
        title: 'Skills',
        description: 'All 12 SkyBlock skills — how to level them efficiently and what rewards they unlock.',
        lastUpdated: '2026-04-05',
        sections: [
            section('overview', 'Skills Overview', 2, [
                callout('Farming First', 'Farming 60 gives the largest single health bonus. Plan for it in mid–late game.'),
                table(
                    ['Skill', 'Max Level', 'Key Bonus at Max', 'Best XP Method'],
                    [
                        ['Farming', '60', '+242 Health', 'Pumpkin/Sugar Cane + Mooshroom Cow'],
                        ['Mining', '60', '+90 Defense', 'Mithril/Gemstone in Dwarven Mines'],
                        ['Combat', '60', '+100 Strength', 'Dungeons, Slayer, mob grinders'],
                        ['Foraging', '50', '+40 Strength', 'Jungle wood + Ocelot'],
                        ['Fishing', '50', '+192 Health', 'Fishing + Dolphin/Whale pet'],
                        ['Enchanting', '60', '+170 Intelligence', 'Experimentation table + anvil'],
                        ['Alchemy', '50', '+85 Intelligence', 'Potion brewing loops'],
                        ['Taming', '50', '+50 Pet Luck', 'Active pet usage'],
                        ['Carpentry', '50', 'Cosmetic', 'Crafting furniture'],
                        ['Runecrafting', '25', 'Cosmetic', 'Crafting runes'],
                        ['Social', '25', 'Cosmetic', 'Playing with others'],
                    ],
                ),
            ]),
            section('farming-xp', 'Farming Crops XP', 2, [
                table(
                    ['Crop', 'XP per action', 'Notes'],
                    [
                        ['Pumpkin', '3.0', 'Fast 2-block harvest'],
                        ['Sugar Cane', '3.0 per block', 'Needs Replenish on hoe'],
                        ['Wheat', '2.4', 'Easiest setup'],
                        ['Carrot / Potato', '3.0', 'Good with Mooshroom Cow'],
                        ['Nether Wart', '5.0', 'Crimson Isle unlock'],
                    ],
                ),
            ]),
            section('mining-xp', 'Mining Locations', 2, [
                table(
                    ['Location', 'Ore', 'Notes'],
                    [
                        ['Gold Mine', 'Gold', 'Early game only'],
                        ['Deep Caverns', 'Iron/Gold/Diamond', 'Pre-Dwarven'],
                        ['Dwarven Mines', 'Mithril', 'Best early-mid XP + Powder'],
                        ['Crystal Hollows', 'Gemstones', 'Best mid-late XP'],
                        ['Mineshaft (HOTM 10)', 'Ores', 'Best late game'],
                    ],
                ),
            ]),
            section('enchanting-xp', 'Enchanting XP Sources', 2, [
                table(
                    ['Source', 'XP', 'Notes'],
                    [
                        ['Enchanting table', 'Low', 'Passive'],
                        ['Experimentation Table', 'Very High', 'Do daily'],
                        ['Combining books', 'Medium', 'Each combine gives XP'],
                        ['Enchanting gear', 'Medium', 'Applies to gear too'],
                    ],
                ),
                callout('Fastest Enchanting XP', 'Experimentation Table daily — best time-to-XP ratio.'),
            ]),
            section('milestones', 'Skill Milestone Targets', 2, [
                table(
                    ['Stage', 'Skill Average Target', 'Priority Skills'],
                    [
                        ['Early Game', '15–20', 'Combat, Foraging'],
                        ['Mid Game', '30–40', 'Farming, Mining, Enchanting'],
                        ['Late Game', '45–55', 'All skills'],
                        ['Endgame', '55–60', 'Farming 60, Combat 60, Fishing 50'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools.slice(0, 3),
    },
    {
        slug: 'accessories',
        title: 'Accessories & Magic Power',
        description: 'How Magic Power works, MP breakpoints, accessory bags, power stones, and tuning.',
        lastUpdated: '2026-04-05',
        sections: [
            section('mp', 'Magic Power (MP)', 2, [
                p('Each unique accessory contributes MP based on rarity. Duplicates in your bag do not add MP.'),
                callout('No duplicate MP', 'Only one of each accessory type counts toward MP.'),
            ]),
            section('breakpoints', 'MP Breakpoints', 2, [
                table(
                    ['MP', 'Effect'],
                    [
                        ['10', 'First bonus'],
                        ['100', 'Moderate stat boost'],
                        ['200', 'Good baseline'],
                        ['300', 'Major bonus — priority target'],
                        ['400', 'Strong'],
                        ['500', 'Big damage multiplier'],
                        ['600', 'Very strong'],
                        ['700', 'Common endgame target'],
                        ['800+', 'Excellent — high cost'],
                    ],
                ),
                callout('Hit 300 MP first', '300 MP is the first breakpoint worth building your bag around.'),
            ]),
            section('bag', 'Accessory Bag Sizes', 2, [
                table(
                    ['Stage', 'Bag Slots'],
                    [
                        ['Early game', '15–25'],
                        ['Mid game', '45–60'],
                        ['Late game', '75–100'],
                        ['Endgame', '100+'],
                    ],
                ),
            ]),
            section('stones', 'Power Stones', 2, [
                table(
                    ['Power Stone', 'Bonus Focus', 'Best For'],
                    [
                        ['Sorrow', 'Strength', 'Berserk / Combat'],
                        ['Fierce', 'Crit Damage', 'Archer / DPS'],
                        ['Spiritual', 'Speed + Int', 'Mage / movement'],
                        ['Silky', 'Farming Fortune', 'Farming'],
                        ['Precise', 'Crit Chance', 'Early-mid players'],
                        ['Bubba', 'Fishing Fortune', 'Fishing builds'],
                    ],
                ),
            ]),
            section('tuning', 'Tuning Recommendations', 2, [
                table(
                    ['Activity', 'Recommended Tuning'],
                    [
                        ['Dungeons Mage', 'Intelligence → Ability Damage'],
                        ['Dungeons Archer', 'Crit Damage → Strength'],
                        ['Farming', 'Farming Fortune'],
                        ['Mining', 'Mining Fortune → Speed'],
                    ],
                ),
            ]),
            section('reforges', 'Accessory Reforges', 2, [
                table(
                    ['Reforge', 'Best For'],
                    [
                        ['Warped', 'Magic Find + damage (popular)'],
                        ['Itchy', 'Crit Damage builds'],
                        ['Lucky', 'Crit Chance builds'],
                        ['Spiked', 'Strength builds'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools.slice(0, 3),
    },
];
