<?php

namespace App\Services;

use App\Models\Guide;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuideCatalogService
{
    public const CATEGORIES = [
        'progression' => 'Progression',
        'game-systems' => 'Game Systems',
        'economy' => 'Economy & Tools',
        'meta' => 'Meta & Info',
    ];

    public const EXTERNAL_TOOLS = [
        ['label' => 'Hypixel Wiki', 'url' => 'https://wiki.hypixel.net/'],
        ['label' => 'Fandom Wiki', 'url' => 'https://hypixel-skyblock.fandom.com/'],
    ];

    public const QUICK_LINKS = [
        ['prompt' => 'Mechanics lookup?', 'label' => 'Hypixel Wiki', 'url' => 'https://wiki.hypixel.net/'],
        ['prompt' => 'Detailed wiki?', 'label' => 'Fandom Wiki', 'url' => 'https://hypixel-skyblock.fandom.com/'],
        ['prompt' => 'Farming calculator?', 'label' => 'SkyblockTools.dev', 'url' => 'https://skyblocktools.dev/farming-profit-calculator'],
        ['prompt' => 'Farming leaderboards?', 'label' => 'EliteFarmers', 'url' => 'https://elitefarmers.com/'],
    ];

    public function __construct(
        private readonly GuideContentNormalizer $normalizer,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        /** @var Collection<int, Guide> $guides */
        $guides = Guide::published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $guides
            ->groupBy('category')
            ->map(function (Collection $items, string $category) {
                $first = $items->first();

                return [
                    'id' => $category,
                    'label' => $first?->category_label ?: self::CATEGORIES[$category] ?? Str::headline($category),
                    'topics' => $items->map(fn (Guide $guide) => [
                        'slug' => $guide->slug,
                        'title' => $guide->title,
                        'description' => $guide->description,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    public function searchIndex(): array
    {
        return Guide::published()
            ->orderBy('sort_order')
            ->get()
            ->flatMap(function (Guide $guide) {
                $entries = [[
                    'slug' => $guide->slug,
                    'title' => $guide->title,
                    'description' => (string) $guide->description,
                    'breadcrumb' => $guide->title,
                    'text' => trim($guide->title.' '.$guide->description),
                ]];

                foreach ($guide->sections ?? [] as $section) {
                    $heading = (string) ($section['heading'] ?? '');
                    if ($heading === '') {
                        continue;
                    }

                    $entries[] = [
                        'slug' => $guide->slug,
                        'title' => $heading,
                        'description' => $guide->title,
                        'breadcrumb' => $guide->title.' > '.$heading,
                        'text' => $guide->title.' '.$heading,
                    ];
                }

                return $entries;
            })
            ->values()
            ->all();
    }

    public function ensureSeeded(): void
    {
        if (Guide::query()->exists()) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->sourceGuides() as $index => $guide) {
                Guide::query()->create([
                    ...$guide,
                    'sections' => $this->normalizer->sections($guide['sections']),
                    'useful_links' => $this->normalizer->usefulLinks($guide['useful_links'] ?? []),
                    'sort_order' => $index + 1,
                    'status' => Guide::STATUS_PUBLISHED,
                    'last_updated_on' => today()->toDateString(),
                    'published_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sourceGuides(): array
    {
        return $this->guidesFromStaticJs() ?: $this->starterGuides();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function guidesFromStaticJs(): array
    {
        if (! function_exists('shell_exec')) {
            return [];
        }

        $topicsPath = base_path('resources/js/data/guides/topics/index.js');
        $navigationPath = base_path('resources/js/data/guides/navigation.js');

        if (! is_file($topicsPath) || ! is_file($navigationPath)) {
            return [];
        }

        $script = <<<'JS'
            import { allTopics } from './resources/js/data/guides/topics/index.js';
            import { guideGroups } from './resources/js/data/guides/navigation.js';

            const meta = new Map();
            for (const group of guideGroups) {
                for (const topic of group.topics) {
                    meta.set(topic.slug, { category: group.id, categoryLabel: group.label });
                }
            }

            console.log(JSON.stringify(allTopics.map((topic) => ({
                slug: topic.slug,
                title: topic.title,
                description: topic.description,
                category: meta.get(topic.slug)?.category ?? 'meta',
                category_label: meta.get(topic.slug)?.categoryLabel ?? 'Meta & Info',
                last_updated_on: topic.lastUpdated ?? null,
                sections: topic.sections ?? [],
                useful_links: topic.usefulLinks ?? [],
            }))));
        JS;

        $command = 'cd '.escapeshellarg(base_path()).' && node --input-type=module -e '.escapeshellarg($script).' 2>/dev/null';
        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_map(fn (array $guide) => [
            ...$guide,
            'last_updated_on' => today()->toDateString(),
            'useful_links' => $this->removeBlockedLinks($guide['useful_links'] ?? []),
        ], $decoded);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function starterGuides(): array
    {
        return [
            $this->starter('early', 'Early Game', 'Structured start: priorities, money making, and must-have resources.', 'progression', [
                'Complete the tutorial, unlock early islands, and set up basic minions.',
                'Prioritize fairy souls, collections, Combat 12, and your first real weapon upgrade.',
            ]),
            $this->starter('mid', 'Mid Game', 'Optimization phase: scale your setup and pick efficient lanes.', 'progression', [
                'Choose one money method and one progression lane instead of spreading upgrades everywhere.',
                'Work on Magical Power, skill averages, pets, and stable dungeon or Garden progression.',
            ]),
            $this->starter('end', 'End Game', 'Meta + efficiency: high-end methods, updates, and min-max resources.', 'progression', [
                'Track patch changes, optimize gear around your active activity, and avoid overpaying during hype windows.',
                'Use profile tools to find missing low-cost upgrades before buying expensive marginal items.',
            ]),
            $this->starter('skills', 'Skills', 'All skills overview, best XP methods, and milestone rewards.', 'progression', [
                'Skills unlock stats, recipes, and SkyBlock XP. Level the skills that support your current goal first.',
                'Daily experiments, Garden milestones, and mining commissions are reliable long-term habits.',
            ]),
            $this->starter('accessories', 'Accessories & MP', 'Magical Power deep dive, accessory bags, tuning, and power stones.', 'progression', [
                'Cheap accessories often beat expensive gear upgrades for damage per coin.',
                'Check your accessory list or a trusted bot for missing accessories, then buy by lowest coin per Magical Power.',
            ]),
            $this->starter('garden', 'Garden', 'Crop milestones, FF basics, best tools, pest farming & profit checks.', 'game-systems', [
                'Garden progression is about Farming Fortune, crop milestones, visitors, and consistent plots.',
                'Upgrade tools and armor in order, then compare crops by profit and XP before switching farms.',
            ]),
            $this->starter('mining', 'Mining', 'HotM pathing, powder, gemstones, gear tiers, and drill upgrades.', 'game-systems', [
                'Mining scales heavily with Heart of the Mountain, powder, pristine, and route quality.',
                'Do commissions first, powder grind before expensive setups, and test rates before committing coins.',
            ]),
            $this->starter('dungeons', 'Dungeons', 'Floor progression, classes, gear paths, secrets, and tutorials.', 'game-systems', [
                'Progress floor by floor, learn secrets, and keep gear requirements realistic for your Catacombs level.',
                'A stable party and correct class role often matter more than one expensive weapon.',
            ]),
            $this->starter('slayers', 'Slayers', 'All 6 bosses, tier requirements, gear, RNG drops, and costs.', 'game-systems', [
                'Start with lower tiers until kills are fast and consistent, then upgrade gear for the next tier.',
                'Track costs, RNG drops, and required unlock levels before grinding a slayer for profit.',
            ]),
            $this->starter('pets', 'Pets', 'Best pets per activity, leveling methods, pet items, and pet score.', 'game-systems', [
                'Pick pets by activity: combat, mining, farming, fishing, or utility.',
                'Pet items and leveling can change performance dramatically, so price the full setup.',
            ]),
            $this->starter('fishing', 'Fishing', 'Regular, lava, trophy fishing - gear, sea creatures, and profit.', 'game-systems', [
                'Fishing needs the right rod, armor, bait, and sea creature chance for the content you are doing.',
                'Lava and trophy fishing have separate progression paths, so do not mix gear assumptions.',
            ]),
            $this->starter('enchanting', 'Enchanting', 'Best enchants per gear type, experimentation table, and XP methods.', 'game-systems', [
                'Do the Experimentation Table daily for efficient Enchanting XP.',
                'Check item requirements and conflicts before applying expensive books.',
            ]),
            $this->starter('crimson', 'Crimson Isle', 'Faction choice, reputation grind, dojo, and Kuudra intro.', 'game-systems', [
                'Crimson Isle progression revolves around reputation, quests, minibosses, and Kuudra preparation.',
                'Pick a faction, learn daily reputation sources, and avoid skipping survivability upgrades.',
            ]),
            $this->starter('kuudra', 'Kuudra', 'All 5 tiers, gear requirements, party roles, and Crimson armor.', 'game-systems', [
                'Kuudra requires clear party roles, preparation, and tier-appropriate gear.',
                'Understand supply routes, crowd control, and profit sources before pushing higher tiers.',
            ]),
            $this->starter('rift', 'The Rift', 'Timecharms, motes farming, enigma souls, and vampire slayer.', 'game-systems', [
                'Rift progression is gated by timecharms and area unlocks.',
                'Plan routes around remaining time, motes, enigma souls, and the next unlock requirement.',
            ]),
            $this->starter('shards', 'Shards & Hunting', 'Best locations, drops, routes, and tracking tools.', 'game-systems', [
                'Shard hunting rewards route knowledge and drop tracking.',
                'Use repeatable routes and log drops so you can compare real rates against expectations.',
            ]),
            $this->starter('money', 'Money Making', 'Farming, mining, flipping, mayor prep - pick your method.', 'economy', [
                'Choose a method that matches your profile stage and setup cost.',
                'Track hourly profit after taxes, downtime, and setup upgrades, not just best-case screenshots.',
            ]),
            $this->starter('collections', 'Collections & Minions', 'Important unlocks, best minion setups, and passive income.', 'economy', [
                'Collections unlock recipes, storage, minions, and profile progress.',
                'Use minions for missing unlocks first, then optimize for passive income.',
            ]),
            $this->starter('mayor', 'Mayors & Events', 'All mayors & perks, election cycle, and prep strategies.', 'economy', [
                'Mayor perks change the best activity for a few days at a time.',
                'Prepare materials before events and avoid buying into peak hype.',
            ]),
            $this->starter('tricks', 'Tricks & Tips', 'Useful SkyBlock tips, QoL tricks, and common pitfalls to avoid.', 'meta', [
                'Set keybinds for menus, warps, backpacks, and the Auction House.',
                'Avoid common mistakes like buying gear without requirements or leaving minions full.',
            ]),
            $this->starter('mods', 'Mods', 'Curated essential mods (SkyHanni, QoL, performance) and safe sources.', 'meta', [
                'Use trusted mods from official sources only, and match the Minecraft version.',
                'SkyHanni, NEU, Firmament, Patcher, and profile tools cover most quality-of-life needs.',
            ]),
            $this->starter('news', 'News & Patches', 'Patch notes, what changed, who it affects.', 'meta', [
                'Read economy changes first, then item reworks, requirements, and mod compatibility notes.',
                'Wait before buying new meta gear unless you understand why the price moved.',
            ]),
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private function starter(string $slug, string $title, string $description, string $category, array $paragraphs): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'category_label' => self::CATEGORIES[$category],
            'last_updated_on' => today()->toDateString(),
            'sections' => [
                [
                    'id' => 'overview',
                    'heading' => 'Overview',
                    'level' => 2,
                    'blocks' => array_map(fn (string $text) => ['type' => 'paragraph', 'text' => $text], $paragraphs),
                ],
                [
                    'id' => 'quick-checks',
                    'heading' => 'Quick Checks',
                    'level' => 2,
                    'blocks' => [
                        [
                            'type' => 'list',
                            'ordered' => false,
                            'items' => [
                                'Check your current profile stats before spending coins.',
                                'Compare upgrades by impact, setup cost, and requirements.',
                                'Revisit this guide after major Hypixel patches.',
                            ],
                        ],
                    ],
                ],
            ],
            'useful_links' => [
                ['label' => 'Hypixel Wiki', 'url' => 'https://wiki.hypixel.net/', 'external' => true],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function removeBlockedLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter($links, function ($link) {
            if (! is_array($link)) {
                return false;
            }

            $haystack = strtolower(($link['label'] ?? '').' '.($link['url'] ?? ''));

            return ! str_contains($haystack, 'skycrypt')
                && ! str_contains($haystack, 'sky.shiiyu.moe')
                && ! str_contains($haystack, 'cofl')
                && ! str_contains($haystack, 'coflnet')
                && ! str_contains($haystack, 'sky.coflnet.com');
        }));
    }
}
