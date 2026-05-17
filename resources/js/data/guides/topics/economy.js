import { section, p, table, callout, list } from '../blocks.js';

const tools = [
    { label: 'SkyCrypt', url: 'https://sky.shiiyu.moe/', external: true },
    { label: 'COFL', url: 'https://sky.coflnet.com/', external: true },
    { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/', external: true },
];

export const economyTopics = [
    {
        slug: 'money',
        title: 'Money Making',
        description: 'Best methods to earn coins at every stage — pick a lane, then optimize it.',
        lastUpdated: '2026-04-05',
        sections: [
            section('pick', 'How to Pick a Method', 2, [
                list([
                    'Match method to your current gear and skill levels.',
                    'Prefer consistent hourly rates over hype spikes.',
                    'Invest profits back into the method before switching.',
                ], true),
                callout('Consistency beats chasing the meta', 'Stick with one method 2–4 weeks before switching.'),
            ]),
            section('early', 'Early Game Methods', 2, [
                table(
                    ['Method', 'Coins/hr', 'Notes'],
                    [
                        ['Hub farming', '100–300k', 'No setup needed'],
                        ['Gold Mine / Deep Caverns', '100–400k', 'XP + coins'],
                        ['Mob drops', '200–500k', 'Combat XP too'],
                    ],
                ),
            ]),
            section('mid', 'Mid Game Methods', 2, [
                table(
                    ['Method', 'Coins/hr', 'Setup Cost', 'Req.'],
                    [
                        ['Garden farming', '2–8M', '2–5M', 'Garden unlocked'],
                        ['Gemstone mining', '3–8M', '5–15M', 'HotM 4+, Drill'],
                        ['Dungeons F5–F6', '2–6M', '5M', 'Cata 18+'],
                        ['Enderman Slayer T3', '1–3M', '1M', 'Ender gear'],
                    ],
                ),
            ]),
            section('late', 'Late / Endgame Methods', 2, [
                table(
                    ['Method', 'Coins/hr', 'Setup Cost', 'Req.'],
                    [
                        ['Pest farming', '20–45M', '50–200M', 'Full setup'],
                        ['Gemstone (optimized)', '8–20M', '50M+', 'HotM 9+, Divan\'s'],
                        ['Kuudra T4/T5', '15–35M', '100M+', 'Crimson gear'],
                        ['Dungeons M5–M7', '10–25M', '80M+', 'Necron, Cata 38+'],
                        ['Flipping / Bazaar', 'Unlimited', 'Know-how', 'No gear'],
                    ],
                ),
                callout('Numbers are estimates', 'Rates vary with gear, mayor buffs, and market conditions.'),
            ]),
            section('flipping', 'Flipping Methods', 2, [
                table(
                    ['Method', 'Risk', 'Skill needed'],
                    [
                        ['Bazaar spread', 'Low', 'Very low'],
                        ['Bazaar flip', 'Low–Med', 'Low'],
                        ['AH flipping', 'Med–High', 'Medium'],
                        ['Mayor prep', 'Medium', 'Medium–High'],
                    ],
                ),
            ]),
            section('mayor-prep', 'Mayor Prep Windows', 2, [
                table(
                    ['Mayor', 'Prep', 'Window'],
                    [
                        ['Diana', 'Stock myth mob drops', 'Mythological Ritual'],
                        ['Marina', 'Stock fish items', 'Fishing XP buff'],
                        ['Cole', 'Stock mithril/gem items', 'Mining buff'],
                        ['Finnegan', 'Stock farming crops', 'Farming buff'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'collections',
        title: 'Collections & Minions',
        description: 'Which collections to unlock first and how to set up minions for passive income.',
        lastUpdated: '2026-04-05',
        sections: [
            section('priority', 'Priority Collections', 2, [
                table(
                    ['Collection', 'Threshold', 'Why It Matters'],
                    [
                        ['Sugar Cane 9', '50,000', 'Enchanted Sugar recipe'],
                        ['Wheat 9', '50,000', 'Enchanted Bread / Bales'],
                        ['Lapis 9', '50,000', 'Lapis Armor (early combat)'],
                        ['Redstone 9', '50,000', 'Redstone Minion, recipes'],
                        ['Coal 4', '5,000', 'Enchanted Coal for fuel'],
                    ],
                ),
                callout('Use minions to AFK collections', 'Place minions for resources you need to unlock recipes.'),
            ]),
            section('minion-tiers', 'Minion Tier Scaling', 2, [
                table(
                    ['Tier', 'Speed', 'Relative Income'],
                    [
                        ['T1', '26s', '1×'],
                        ['T5', '13s', '2×'],
                        ['T7', '9.5s', '2.7×'],
                        ['T9', '7s', '3.7×'],
                        ['T11', '5s', '~5×'],
                    ],
                ),
            ]),
            section('best-minions', 'Best Minions for Passive Coins', 2, [
                table(
                    ['Minion', 'Tier', 'Coins/day', 'Notes'],
                    [
                        ['Sugar Cane', 'T11', '~400k', 'Easy setup'],
                        ['Wheat', 'T11', '~300k', 'Stable NPC price'],
                        ['Slime', 'T11', '~500k', 'Bazaar price'],
                        ['Lapis', 'T11', '~600k', 'Enchanted Lapis'],
                        ['Ghast', 'T11', '~1.2M', 'Higher craft cost'],
                    ],
                ),
            ]),
            section('fuel', 'Minion Fuel', 2, [
                table(
                    ['Fuel', 'Speed Boost', 'Duration'],
                    [
                        ['Catalyst', '+25%', 'Infinite'],
                        ['Hamster Wheel', '+25%', 'Infinite'],
                        ['Foul Flesh', '+20%', '3 days'],
                        ['Enchanted Lava Bucket', '+25%', '12 days'],
                    ],
                ),
            ]),
            section('slots', 'Minion Slot Unlocks', 2, [
                table(
                    ['Unique minions crafted', 'Total slots'],
                    [
                        ['0', '5'],
                        ['5', '6'],
                        ['15', '7'],
                        ['30', '9'],
                        ['50', '11'],
                        ['100', '15'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'mayor',
        title: 'Mayors & Events',
        description: 'All SkyBlock mayors, perks, elections, and prep strategies.',
        lastUpdated: '2026-04-05',
        sections: [
            section('elections', 'How Elections Work', 2, [
                p('Mayors rotate on a ~5-day election cycle. Special mayors (Scorpius, Derpy, Jerry) appear rarely.'),
                callout('Special mayors', 'Scorpius, Derpy, and Jerry replace normal perk sets — plan accordingly.'),
            ]),
            section('perks', 'All Mayor Perks', 2, [
                table(
                    ['Mayor', 'Theme', 'Key Perks'],
                    [
                        ['Aatrox', 'Combat', '+Slayer XP, double drops'],
                        ['Cole', 'Mining', '+Mining Fortune, Powder'],
                        ['Diana', 'Hunt', 'Mythological Ritual, Pelts'],
                        ['Marina', 'Fishing', '+Fishing XP, Sea Creature Chance'],
                        ['Finnegan', 'Farming', 'Crop milestone XP boost'],
                        ['Foxy', 'Fun', '+Speed, Lucky Wheel'],
                        ['Jerry', 'Wild', '3 random perks from any mayor'],
                        ['Scorpius', 'Ghost', '+SkyBlock XP/day, Bribe'],
                    ],
                ),
                callout('Perks change between elections', 'Check current election perks before prepping stock.'),
            ]),
            section('prep', 'Preparing for a Mayor', 2, [
                list([
                    'Identify which mayor wins next (community trackers).',
                    'Stock items that spike under that mayor\'s buff.',
                    'Don\'t over-invest — margins compress when everyone preps.',
                    'Use COFL to track price trends.',
                ], true),
            ]),
        ],
        usefulLinks: [
            ...tools,
            { label: 'Election tracker', url: 'https://skyblock.tools/election', external: true },
        ],
    },
];
