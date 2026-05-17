import { section, p, table, callout, list, links } from '../blocks.js';

const tools = [
    { label: 'SkyCrypt', url: 'https://sky.shiiyu.moe/', external: true },
    { label: 'COFL', url: 'https://sky.coflnet.com/', external: true },
    { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/', external: true },
];

export const gameSystemTopics = [
    {
        slug: 'garden',
        title: 'Garden',
        description: 'The Garden farming system — Farming Fortune, crop milestones, pests, visitors, and contests.',
        lastUpdated: '2026-04-05',
        sections: [
            section('intro', 'What is the Garden?', 2, [
                p('A personal farming plot with crop milestones, visitors, Jacob\'s contests, and pest farming at high investment.'),
                callout('How to unlock', 'Complete the Farming Islands quest chain from the SkyBlock menu.'),
            ]),
            section('ff', 'Farming Fortune (FF)', 2, [
                p('FF multiplies crop drops. Gear > Pet for raw FF in most setups.'),
                table(
                    ['Source', 'FF contribution'],
                    [
                        ['Hoe upgrades & enchants', 'Large'],
                        ['Armor (Rancher\'s, etc.)', 'Moderate'],
                        ['Pet (Elephant, Mooshroom)', 'Moderate'],
                        ['Accessories + tuning', 'Moderate'],
                        ['Jacob medals', 'Small–moderate'],
                        ['Reforge on hoe', 'Moderate'],
                        ['Garden level', 'Scales over time'],
                    ],
                ),
                callout('Gear > Pet for FF', 'Upgrade hoe and armor before chasing a perfect pet.'),
            ]),
            section('crops', 'Best Crops by Stage', 2, [
                table(
                    ['Crop', 'Early FF', 'Late FF', 'Notes'],
                    [
                        ['Wheat', 'Easy', 'Moderate', 'Good starter'],
                        ['Carrot/Potato', 'Good', 'Good', 'Mooshroom Cow synergy'],
                        ['Pumpkin', 'Good', 'Strong', 'Fast harvest'],
                        ['Sugar Cane', 'Good', 'Strong', 'Needs Replenish'],
                        ['Mushroom', 'Mid', 'Very strong', 'Pest farming meta'],
                        ['Nether Wart', 'Mid', 'Strong', 'High XP'],
                    ],
                ),
            ]),
            section('pests', 'Pest Farming', 2, [
                p('High-end pest farming on mushroom or wart can reach 20–40M coins/hr with full setup.'),
                callout('Pests require Greenhouse unlock', 'Check Garden milestones before investing in pest gear.'),
                list(['Sprayonator + pest traps', 'High FF hoe', 'Pest armor set', 'Track visitor orders for bonus items']),
            ]),
        ],
        usefulLinks: [
            ...tools,
            { label: 'Farming profit calculator', url: 'https://skyblocktools.dev/farming-profit-calculator', external: true },
        ],
    },
    {
        slug: 'mining',
        title: 'Mining',
        description: 'Complete mining guide — Heart of the Mountain, powder, gear progression, and methods.',
        lastUpdated: '2026-04-05',
        sections: [
            section('hotm', 'Heart of the Mountain (HotM)', 2, [
                callout('Unlock Dwarven Mines first', 'Give Rhys 15 enchanted ores after reaching Deep Caverns.'),
                table(
                    ['HotM Level', 'Key Unlock'],
                    [
                        ['1', 'Unlock HotM tree'],
                        ['2', 'Mining Speed II'],
                        ['3', 'Quick Forge'],
                        ['4', 'Crystal Hollows access'],
                        ['5', 'Efficient Miner'],
                        ['6', 'Mining Speed Boost ability'],
                        ['7', 'Vein Seeker'],
                        ['8', 'Glacite Tunnels'],
                        ['9', 'Mineshaft access'],
                        ['10', 'Full tree'],
                    ],
                ),
            ]),
            section('powder', 'Powder Types', 2, [
                table(
                    ['Type', 'Where From', 'Used For'],
                    [
                        ['Mithril Powder', 'Dwarven Mines, Glacite', 'HotM upgrades'],
                        ['Gemstone Powder', 'Crystal Hollows', 'HotM upgrades'],
                    ],
                ),
            ]),
            section('areas', 'Mining Areas', 2, [
                table(
                    ['Area', 'Access', 'Best For'],
                    [
                        ['Gold Mine', 'Default', 'Tutorial only'],
                        ['Deep Caverns', 'Hub', 'Early pre-Dwarven'],
                        ['Dwarven Mines', 'HotM 1', 'Mithril, early Powder'],
                        ['Crystal Hollows', 'HotM 4', 'Gemstones, Powder'],
                        ['Glacite Tunnels', 'HotM 8', 'Powder, Glacite Ore'],
                        ['Mineshaft', 'HotM 9', 'End-game Powder + XP'],
                    ],
                ),
            ]),
            section('mf', 'Mining Fortune Sources', 2, [
                table(
                    ['Source', 'MF Bonus'],
                    [
                        ['HotM tree nodes', 'Up to +250'],
                        ['Divan\'s Armor', 'Large per piece'],
                        ['Mining pet', 'Varies'],
                        ['Gauntlet equipment', '+50–100'],
                        ['Reforge: Refined', 'Moderate'],
                    ],
                ),
                callout('Fortune > Speed for profit', 'Mining Fortune beats raw speed for coins/hr.'),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'dungeons',
        title: 'Dungeons',
        description: 'Full dungeons guide — classes, floors, progression, gear, and efficiency.',
        lastUpdated: '2026-04-05',
        sections: [
            section('floors', 'Floor Progression', 2, [
                table(
                    ['Floor', 'Cata Req.', 'Boss', 'Notable Drops'],
                    [
                        ['F1', 'None', 'Bonzo', 'Basic loot'],
                        ['F2', 'None', 'Scarf', 'Bonzo Staff, Scarf gear'],
                        ['F3', 'None', 'Professor', 'Adaptive Armor'],
                        ['F4', 'Cata 12', 'Thorn', 'Shadow Assassin'],
                        ['F5', 'Cata 18', 'Livid', 'Livid Dagger'],
                        ['F6', 'Cata 24', 'Sadan', 'Necron/Storm/Maxor/Goldor'],
                        ['F7', 'Cata 28', 'Necron', 'Hyperion, Wither Blade'],
                        ['M1', 'Cata 30', 'Bonzo+', 'Recombobulator 3000'],
                        ['M7', 'Cata 43', 'Necron+', 'Wither Essence farming'],
                    ],
                ),
            ]),
            section('classes', 'Dungeon Classes', 2, [
                table(
                    ['Class', 'Role', 'Passive Bonus', 'Best For'],
                    [
                        ['Archer', 'DPS', 'Bow damage', 'Consistent ranged DPS'],
                        ['Berserk', 'Melee DPS', 'Sword damage', 'AoE clear'],
                        ['Mage', 'Ability DPS', 'Ability damage + Int', 'Hyperion builds'],
                        ['Healer', 'Support', 'Heal teammates', 'High floors survival'],
                        ['Tank', 'Frontline', 'Damage reduction', 'Absorbing boss hits'],
                    ],
                ),
            ]),
            section('score', 'Run Score Breakdown', 2, [
                table(
                    ['Category', 'Weight', 'How to Maximize'],
                    [
                        ['Exploration', '60 pts', 'All rooms + secrets'],
                        ['Speed', '40 pts', 'Sub 20min for S'],
                        ['Skill', '40 pts', 'Low deaths + mob kill %'],
                        ['Bonus', 'varies', 'Crypts, treasure hunter'],
                    ],
                ),
                callout('Secrets tracker', 'Use SkyHanni or similar to track secrets per run.'),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'slayers',
        title: 'Slayers',
        description: 'All 6 slayer types, XP requirements, boss tiers, and key drops.',
        lastUpdated: '2026-04-05',
        sections: [
            section('types', 'Slayer Types', 2, [
                table(
                    ['Slayer', 'Location', 'Boss', 'Key Drops'],
                    [
                        ['Zombie', 'Graveyard', 'Revenant Horror', 'Revenant Catalyst, Undead Sword'],
                        ['Spider', 'Spider\'s Den', 'Tarantula Broodfather', 'Tarantula Boots'],
                        ['Wolf', 'Howling Cave', 'Sven Packmaster', 'Overflux, Hamster Wheel'],
                        ['Enderman', 'The End', 'Voidgloom Seraph', 'Etherwarp, Enderman Sword'],
                        ['Blaze', 'Crimson Isle', 'Inferno Demonlord', 'Lava Flame pet'],
                        ['Vampire', 'Vampiric Mine', 'Riftstalker Bloodfiend', 'Vampire Fang'],
                    ],
                ),
            ]),
            section('levels', 'Slayer Level Requirements', 2, [
                table(
                    ['Level', 'XP Required', 'Rank'],
                    [
                        ['1', '10', 'Noob'],
                        ['3', '250', 'Skilled'],
                        ['5', '5,000', 'Expert'],
                        ['7', '100,000', 'Expert'],
                        ['9', '1,000,000', 'Grandmaster'],
                    ],
                ),
            ]),
            section('tiers', 'Boss Tiers', 2, [
                table(
                    ['Tier', 'Slayer XP', 'Approx. Cost', 'Req. Level'],
                    [
                        ['T1', '5', '100–500 coins', 'None'],
                        ['T2', '25', '2,000 coins', 'Level 1'],
                        ['T3', '100–200', '10,000 coins', 'Level 2–3'],
                        ['T4', '400–500', '50,000 coins', 'Level 3–4'],
                    ],
                ),
                callout('Run T3 until T4 is reliable', 'Don\'t jump to T4 until you kill bosses consistently.'),
            ]),
            section('drops', 'Key Drops', 2, [
                table(
                    ['Item', 'From', 'Why'],
                    [
                        ['Tarantula Boots', 'Spider T2+', 'Best combat boots until endgame'],
                        ['Overflux', 'Wolf T3+', 'Speed build accessory'],
                        ['Etherwarp Conduit', 'Enderman T4', 'AOTD teleport upgrade'],
                        ['Enderman Sword', 'Enderman T1+', 'Good early-mid weapon'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'pets',
        title: 'Pets',
        description: 'Everything about SkyBlock pets — best per activity, pet items, and leveling.',
        lastUpdated: '2026-04-05',
        sections: [
            section('rarity', 'Pet Rarities & Stats', 2, [
                table(
                    ['Rarity', 'Stat Multiplier', 'Magic Power'],
                    [
                        ['Common', '1×', '3'],
                        ['Uncommon', '1.2×', '5'],
                        ['Rare', '1.5×', '8'],
                        ['Epic', '2×', '12'],
                        ['Legendary', '2.5×', '16'],
                        ['Mythic', '3×', '22'],
                    ],
                ),
            ]),
            section('by-activity', 'Best Pets by Activity', 2, [
                table(
                    ['Activity', 'Best Pet', 'Budget Alternative'],
                    [
                        ['Farming (crops)', 'Leg. Elephant', 'Leg. Rabbit'],
                        ['Farming (mushroom)', 'Leg. Mooshroom Cow', 'Leg. Elephant'],
                        ['Mining', 'Leg. Mole', 'Leg. Quick Claw'],
                        ['Dungeons (Mage)', 'Leg. Ender Dragon', 'Leg. Baby Yeti'],
                        ['Dungeons (Archer)', 'Leg. Baby Yeti', 'Leg. Tiger'],
                        ['Combat / Slayers', 'Leg. Tiger', 'Leg. Lion'],
                        ['Fishing', 'Leg. Whale', 'Leg. Dolphin'],
                        ['Foraging', 'Leg. Ocelot', 'Rare Ocelot'],
                    ],
                ),
                callout('Pets are activity-specific', 'Swap pets when switching activities — SkyHanni can automate this.'),
            ]),
            section('items', 'Pet Items', 2, [
                table(
                    ['Pet Item', 'Effect', 'Best For'],
                    [
                        ['Tier Boost', '+1 rarity visual', 'Budget upgrades'],
                        ['Lucky Clover', '+Pet Luck', 'Any'],
                        ['Ender Artifact', '+Attack Speed vs Endermen', 'Voidgloom'],
                        ['Farming Exp Boost', '+Farming XP', 'Elephant / Rabbit'],
                        ['Textbook', '+5 Int per level', 'Mage builds'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'fishing',
        title: 'Fishing',
        description: 'Rod progression, sea creatures, lava fishing, and trophy fishing.',
        lastUpdated: '2026-04-05',
        sections: [
            section('rods', 'Rod Progression', 2, [
                table(
                    ['Rod', 'Where to get', 'Notes'],
                    [
                        ['Rod of Legends', 'Fisherman / AH', 'Starting rod'],
                        ['Auger Rod', 'Crafted', 'Adds Sea Creature Chance'],
                        ['Shredder', 'Trophy Fish craft', 'Best for sea creatures'],
                        ['Rod of the Sea', 'Crafted', 'Endgame AoE rod'],
                    ],
                ),
                callout('Shredder First', 'Prioritize sea creature fishing for coins once you have SCC gear.'),
            ]),
            section('scc', 'Sea Creature Chance Sources', 2, [
                table(
                    ['Source', 'SCC Bonus'],
                    [
                        ['Fishing Level', '+0.2% per level'],
                        ['Rod enchant: Expertise', 'Up to +10%'],
                        ['Rod enchant: Blessing', '+12.5%'],
                        ['Dolphin/Whale pet', 'Passive per level'],
                        ['Sonar Device', '+5%'],
                    ],
                ),
            ]),
            section('enchants', 'Rod Enchants', 2, [
                table(
                    ['Enchant', 'Max Level', 'Effect'],
                    [
                        ['Blessing', '5', '+12.5% SCC at max'],
                        ['Expertise', '10', '+10% SCC, +Fishing XP'],
                        ['Frail', '6', 'Reduces sea creature HP'],
                        ['Magnet', '6', 'Pulls nearby fish'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'enchanting',
        title: 'Enchanting',
        description: 'Important enchantments for weapons, bows, armor, tools, and ultimate enchants.',
        lastUpdated: '2026-04-05',
        sections: [
            section('weapons', 'Key Weapon Enchantments', 2, [
                table(
                    ['Enchant', 'Max', 'Effect', 'Priority'],
                    [
                        ['Sharpness', '6', '+Damage', 'Essential'],
                        ['Critical', '5', '+Crit Damage', 'High'],
                        ['Giant Killer', '5', '+Damage vs high HP', 'Dungeons'],
                        ['Prosecute', '5', '+Damage below 50% HP', 'Strong AoE'],
                        ['Execute', '5', 'Executes low-HP mobs', 'Dungeons'],
                    ],
                ),
            ]),
            section('bows', 'Key Bow Enchantments', 2, [
                table(
                    ['Enchant', 'Max', 'Effect', 'Priority'],
                    [
                        ['Power', '6', '+Arrow damage', 'Essential'],
                        ['Overload', '5', '+Crit stats', 'High'],
                        ['Dragon Hunter', '4', '+Damage to dragons', 'Dragon fights'],
                    ],
                ),
            ]),
            section('armor', 'Key Armor Enchantments', 2, [
                table(
                    ['Enchant', 'Max', 'Effect', 'Priority'],
                    [
                        ['Protection', '6', '+Defense', 'Essential'],
                        ['Growth', '6', '+Max Health', 'Essential'],
                        ['Strong Mana', '6', '+Max Mana', 'Mage'],
                        ['True Protection', '1', 'Reduce true damage', 'Endgame'],
                    ],
                ),
            ]),
            section('ultimate', 'Ultimate Enchantments', 2, [
                table(
                    ['Ultimate Enchant', 'Best For'],
                    [
                        ['Ultimate Wise', 'Mage mana regen'],
                        ['Ultimate Chimera', 'Versatile DPS'],
                        ['Ultimate Soul Eater', 'Berserk / high kill rate'],
                        ['Ultimate Rend', 'Slayer / boss fights'],
                    ],
                ),
                callout('Ultimate enchants are expensive', 'Plan one ultimate per piece — don\'t spread thin.'),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'crimson',
        title: 'Crimson Isle',
        description: 'Faction system, reputation, Dojo, Blaze Slayer, and Kuudra unlock path.',
        lastUpdated: '2026-04-05',
        sections: [
            section('intro', 'What is the Crimson Isle?', 2, [
                p('End-game island with factions, reputation, Dojo, Blaze Slayer, lava fishing, and Kuudra.'),
                callout('How to unlock', 'Reach Combat 22 and complete the quest chain from Elle in the Hub.'),
            ]),
            section('reputation', 'Reputation Sources', 2, [
                table(
                    ['Source', 'Reputation Gained'],
                    [
                        ['Completing quests', '100–500'],
                        ['Killing Crimson mobs', '1–5 per kill'],
                        ['Turning in faction items', 'Variable'],
                        ['Dojo completion', '50–200'],
                    ],
                ),
                callout('Faction choice is permanent', 'Pick Mages or Barbarians carefully — you cannot switch.'),
            ]),
            section('blaze', 'Blaze Slayer Tiers', 2, [
                table(
                    ['Tier', 'Slayer XP', 'Key Drop'],
                    [
                        ['T1', '5', 'Ember Rod'],
                        ['T2', '25', 'Magma Lord pieces'],
                        ['T3', '100', 'Lava Flame pet shards'],
                        ['T4', '400', 'Fiery Kuudra pieces'],
                    ],
                ),
                callout('Fire resistance is essential', 'Bring fire resistance potions or gear for Blaze Slayer.'),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'kuudra',
        title: 'Kuudra',
        description: 'All 5 tiers, gear requirements, party roles, attributes, and Crimson armor.',
        lastUpdated: '2026-04-05',
        sections: [
            section('tiers', 'Kuudra Tiers', 2, [
                table(
                    ['Tier', 'Difficulty', 'Entry Req.', 'Key Drops'],
                    [
                        ['T1 Basic', 'Very Easy', 'None', 'Basic Crimson pieces'],
                        ['T2 Hot', 'Easy', 'Basic combat gear', 'Better Crimson'],
                        ['T3 Burning', 'Moderate', '~400 MP', 'Good Crimson pieces'],
                        ['T4 Fiery', 'Hard', '600+ MP', 'Best non-Infernal'],
                        ['T5 Infernal', 'Extreme', '700+ MP team', 'Infernal attributes'],
                    ],
                ),
                callout('T5 is gear-dependent', 'Don\'t queue T5 without a coordinated party and optimized gear.'),
            ]),
            section('attributes', 'Attribute System', 2, [
                table(
                    ['Attribute', 'Effect', 'Best For'],
                    [
                        ['Mana Pool', '+Mana per regen tick', 'Mage'],
                        ['Dominance', '+Crit Damage scaling', 'Archer'],
                        ['Blazing Fortune', '+Crimson mob luck', 'Blaze Slayer'],
                        ['Fortitude', '+Defense', 'Tank'],
                    ],
                ),
            ]),
            section('roles', 'Party Roles', 2, [
                table(
                    ['Role', 'Job', 'Gear Focus'],
                    [
                        ['Supplier', 'Collect build materials', 'Speed, survivability'],
                        ['Builder', 'Construct fuel cells', 'Speed, survivability'],
                        ['Damage', 'DPS Kuudra', 'Max damage'],
                        ['Tank/Support', 'Survive hits', 'Defense, health'],
                    ],
                ),
            ]),
            section('armor', 'Crimson Armor Sets', 2, [
                table(
                    ['Set', 'Notes'],
                    [
                        ['Molten Armor', 'Cheaper entry Kuudra set'],
                        ['Crimson Armor', 'Main Kuudra set'],
                        ['Aurora Armor', 'Magic/Int variant'],
                        ['Fervor Armor', 'Best DPS for T5'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'rift',
        title: 'The Rift',
        description: 'Timecharms, Motes farming, Enigma Souls, Vampire Slayer, and progression.',
        lastUpdated: '2026-04-05',
        sections: [
            section('intro', 'What is the Rift?', 2, [
                p('Alternate dimension with Motes currency, unique progression, and Vampire Slayer.'),
                callout('Rift is isolated', 'Gear and stats work differently — read guides before bringing hub gear expectations.'),
            ]),
            section('timecharms', 'Timecharms', 2, [
                table(
                    ['Timecharm', 'Zone Unlocked', 'How to Get'],
                    [
                        ['Wyld Wynd', 'Wyld Wynd area', 'Quest chain'],
                        ['Mirrorverse', 'Mirror dimension', 'Progress quest'],
                        ['Vampiric', 'Vampiric Mine', 'Mid-progression'],
                        ['Living Cave', 'Underground cave', 'Quest'],
                        ['Lagoon', 'Lagoon area', 'Quest'],
                    ],
                ),
            ]),
            section('motes', 'Motes Farming Methods', 2, [
                table(
                    ['Method', 'Motes/hr', 'Notes'],
                    [
                        ['Montezuma\'s Abyss', 'High', 'Best mob density'],
                        ['Vampire Slayer quests', 'Moderate', 'Doubles as slayer XP'],
                        ['Rift quests', 'Variable', 'One-time high rewards'],
                        ['Wyld Wynd mobs', 'Moderate', 'Easy access'],
                    ],
                ),
            ]),
            section('vampire', 'Vampire Slayer Tiers', 2, [
                table(
                    ['Tier', 'Motes Cost', 'Slayer XP', 'Key Drop'],
                    [
                        ['T1', 'Low', '5', 'Vampire Fang'],
                        ['T2', 'Moderate', '25', 'Vampire Fang, accessories'],
                        ['T3', 'High', '100', 'Rare drops'],
                        ['T4', 'Very High', '400', 'Best drops'],
                    ],
                ),
                callout('Vampire boss regen', 'Learn the heal-cancel mechanic before pushing high tiers.'),
            ]),
        ],
        usefulLinks: tools,
    },
    {
        slug: 'shards',
        title: 'Shards & Hunting',
        description: 'Bestiary, mob grinding routes, and Magic Find from Bestiary milestones.',
        lastUpdated: '2026-04-05',
        sections: [
            section('mf', 'Magic Find from Bestiary', 2, [
                table(
                    ['Milestone', 'Magic Find Gained'],
                    [
                        ['Every 10-kill milestone', '+1 MF'],
                        ['Level 100 family completion', '+5 MF bonus'],
                        ['Full Bestiary', 'Large MF reward'],
                    ],
                ),
                callout('Focus on high-density mobs first', 'Spider\'s Den and Graveyard are efficient early families.'),
            ]),
            section('families', 'Bestiary Families', 2, [
                table(
                    ['Family', 'Key Mobs', 'Bonus'],
                    [
                        ['Undead', 'Zombies, Wither Skeletons', 'Magic Find'],
                        ['Arthropod', 'Spiders', 'Magic Find'],
                        ['Ender', 'Endermen', 'Magic Find'],
                        ['Blaze', 'Blazes, Pigmen', 'Defense'],
                        ['Boss', 'Slayer bosses', 'Large MF'],
                        ['Sea', 'Sea creatures', 'Magic Find'],
                    ],
                ),
            ]),
            section('routes-early', 'Mob Grinding Routes — Early', 2, [
                table(
                    ['Location', 'Mobs', 'Notes'],
                    [
                        ['Spider\'s Den', 'Spiders', 'Fast kills, Arthropod XP'],
                        ['Hub Graveyard', 'Zombies', 'Undead family'],
                        ['Blazing Fortress', 'Blazes', 'Blaze family'],
                    ],
                ),
            ]),
            section('routes-late', 'Mob Grinding Routes — Late', 2, [
                table(
                    ['Location', 'Mobs', 'Notes'],
                    [
                        ['Dungeons M5–M7', 'Boss mobs', 'Boss family + Combat XP'],
                        ['Crystal Hollows', 'Unique mobs', 'Hollows Bestiary'],
                        ['Kuudra runs', 'Kuudra-specific', 'Unique entries'],
                    ],
                ),
            ]),
        ],
        usefulLinks: tools,
    },
];
