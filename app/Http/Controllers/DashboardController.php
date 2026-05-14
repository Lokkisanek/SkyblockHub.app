<?php

namespace App\Http\Controllers;

use App\Models\UserDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const GRID_COLUMNS = 20;

    private const GRID_ROWS = 20;

    private const MAX_WIDGETS = 30;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_WIDGET_TYPES = [
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
        'event_timers_widget',
        'skyblock_calendar_widget',
        'mayor_status_widget',
        'bazaar_top_widget',
        'leaderboard_rank_widget',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $slotIndex = 1;

        $dashboard = null;

        if ($user) {
            $dashboard = UserDashboard::query()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'slot_index' => $slotIndex],
                    [
                        'name' => 'Main Dashboard',
                        'grid_columns' => self::GRID_COLUMNS,
                        'grid_rows' => self::GRID_ROWS,
                        'is_public' => false,
                    ]
                )
                ->load('widgets');

            $dashboard->setRelation(
                'widgets',
                $dashboard->widgets
                    ->filter(fn ($widget) => in_array($widget->type, self::ALLOWED_WIDGET_TYPES, true))
                    ->values()
            );

            if ($dashboard->grid_columns !== self::GRID_COLUMNS || $dashboard->grid_rows !== self::GRID_ROWS) {
                $dashboard->update([
                    'grid_columns' => self::GRID_COLUMNS,
                    'grid_rows' => self::GRID_ROWS,
                ]);

                $dashboard->refresh()->load('widgets');
            }

        }

        return Inertia::render('Dashboard', [
            'canEditDashboard' => (bool) ($user && $user->is_mc_linked),
            'requiresLogin' => ! $user,
            'requiresMinecraftLink' => (bool) ($user && ! $user->is_mc_linked),
            'dashboard' => $dashboard ? [
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'slot_index' => $dashboard->slot_index,
                'is_public' => $dashboard->is_public,
                'grid_columns' => $dashboard->grid_columns,
                'grid_rows' => $dashboard->grid_rows,
                'widgets' => $dashboard->widgets->map(fn ($widget) => [
                    'id' => $widget->id,
                    'type' => $widget->type,
                    'title' => $widget->title,
                    'x' => $widget->x,
                    'y' => $widget->y,
                    'w' => $widget->w,
                    'h' => $widget->h,
                    'settings' => $widget->settings ?? [],
                ])->values(),
            ] : null,
            'widgetTemplates' => $this->widgetTemplates(),
        ]);
    }

    public function visit(string $minecraftUuid): Response
    {
        $dashboard = UserDashboard::query()
            ->where('is_public', true)
            ->where('slot_index', 1)
            ->whereHas('user', function ($query) use ($minecraftUuid): void {
                $query->where('minecraft_uuid', $minecraftUuid);
            })
            ->firstOrFail()
            ->load('widgets');

        $dashboard->setRelation(
            'widgets',
            $dashboard->widgets
                ->filter(fn ($widget) => in_array($widget->type, self::ALLOWED_WIDGET_TYPES, true))
                ->values()
        );

        return Inertia::render('Dashboard', [
            'canEditDashboard' => false,
            'requiresLogin' => false,
            'requiresMinecraftLink' => false,
            'dashboard' => [
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'slot_index' => $dashboard->slot_index,
                'is_public' => $dashboard->is_public,
                'grid_columns' => $dashboard->grid_columns,
                'grid_rows' => $dashboard->grid_rows,
                'widgets' => $dashboard->widgets->map(fn ($widget) => [
                    'id' => $widget->id,
                    'type' => $widget->type,
                    'title' => $widget->title,
                    'x' => $widget->x,
                    'y' => $widget->y,
                    'w' => $widget->w,
                    'h' => $widget->h,
                    'settings' => $widget->settings ?? [],
                ])->values(),
            ],
            'widgetTemplates' => $this->widgetTemplates(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $slotIndex = 1;

        if (! $user || ! $user->is_mc_linked) {
            return back()->withErrors([
                'dashboard' => 'Dashboard can be edited only after Minecraft account linking.',
            ]);
        }

        $data = $request->validate([
            'is_public' => ['required', 'boolean'],
            'widgets' => ['required', 'array', 'max:'.self::MAX_WIDGETS],
            'widgets.*.id' => ['nullable', 'integer'],
            'widgets.*.type' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_WIDGET_TYPES)],
            'widgets.*.title' => ['nullable', 'string', 'max:80'],
            'widgets.*.x' => ['required', 'integer', 'min:1', 'max:'.self::GRID_COLUMNS],
            'widgets.*.y' => ['required', 'integer', 'min:1', 'max:'.self::GRID_ROWS],
            'widgets.*.w' => ['required', 'integer', 'min:1', 'max:'.self::GRID_COLUMNS],
            'widgets.*.h' => ['required', 'integer', 'min:1', 'max:'.self::GRID_ROWS],
            'widgets.*.settings' => ['nullable', 'array'],
        ]);

        $widgets = collect($data['widgets'])
            ->map(function (array $widget) {
                $x = (int) $widget['x'];
                $y = (int) $widget['y'];
                $w = (int) $widget['w'];
                $h = (int) $widget['h'];

                $definition = $this->widgetDefinition((string) $widget['type']);

                if (! $definition) {
                    return null;
                }

                $minSize = $definition['min_size'];

                if ($w < $minSize['w'] || $h < $minSize['h']) {
                    return null;
                }

                return [
                    'id' => $widget['id'] ?? null,
                    'type' => (string) $widget['type'],
                    'title' => $widget['title'] ?? null,
                    'x' => $x,
                    'y' => $y,
                    'w' => $w,
                    'h' => $h,
                    'settings' => (array) ($widget['settings'] ?? []),
                    'end_x' => $x + $w - 1,
                    'end_y' => $y + $h - 1,
                ];
            })
            ->filter()
            ->values();

        if ($widgets->isEmpty()) {
            return back()->withErrors([
                'dashboard' => 'Please add at least one valid widget before saving.',
            ]);
        }

        $outOfGrid = $widgets->first(fn (array $widget) => $widget['end_x'] > self::GRID_COLUMNS || $widget['end_y'] > self::GRID_ROWS);

        if ($outOfGrid) {
            return back()->withErrors([
                'dashboard' => 'One or more widgets are outside the 20x20 grid.',
            ]);
        }

        $occupied = [];
        foreach ($widgets as $widget) {
            for ($row = $widget['y']; $row <= $widget['end_y']; $row++) {
                for ($col = $widget['x']; $col <= $widget['end_x']; $col++) {
                    $key = $col.'-'.$row;
                    if (isset($occupied[$key])) {
                        return back()->withErrors([
                            'dashboard' => 'Widgets overlap. Please adjust layout and try saving again.',
                        ]);
                    }
                    $occupied[$key] = true;
                }
            }
        }

        $dashboard = UserDashboard::query()
            ->firstOrCreate(
                ['user_id' => $user->id, 'slot_index' => $slotIndex],
                [
                    'name' => 'Main Dashboard',
                    'grid_columns' => self::GRID_COLUMNS,
                    'grid_rows' => self::GRID_ROWS,
                    'is_public' => false,
                ]
            );

        DB::transaction(function () use ($dashboard, $widgets, $data): void {
            $dashboard->update([
                'is_public' => (bool) $data['is_public'],
                'grid_columns' => self::GRID_COLUMNS,
                'grid_rows' => self::GRID_ROWS,
            ]);

            $persistedIds = $widgets
                ->pluck('id')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            $dashboard->widgets()
                ->whereNotIn('id', $persistedIds->all())
                ->delete();

            foreach ($widgets as $index => $widget) {
                $payload = [
                    'type' => $widget['type'],
                    'title' => $widget['title'],
                    'x' => $widget['x'],
                    'y' => $widget['y'],
                    'w' => $widget['w'],
                    'h' => $widget['h'],
                    'settings' => $widget['settings'],
                    'sort_order' => $index,
                ];

                if (is_numeric($widget['id'])) {
                    $dashboard->widgets()
                        ->where('id', (int) $widget['id'])
                        ->update($payload);
                } else {
                    $dashboard->widgets()->create($payload);
                }
            }
        });

        return back()->with('success', 'Dashboard saved.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function widgetTemplates(): array
    {
        return array_values($this->widgetDefinitionMap());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function widgetDefinition(string $type): ?array
    {
        return $this->widgetDefinitionMap()[$type] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function widgetDefinitionMap(): array
    {
        return [
            'skin_view_widget' => [
                'type' => 'skin_view_widget',
                'name' => '3D Skin View',
                'description' => 'Live rotating 3D model pulled from the linked profile.',
                'default_size' => ['w' => 3, 'h' => 4],
                'min_size' => ['w' => 3, 'h' => 4],
                'default_title' => '3D Skin View',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'skin',
            ],
            'inventory_gui_widget' => [
                'type' => 'inventory_gui_widget',
                'name' => 'Inventory GUI',
                'description' => 'A clean inventory window preview with live items.',
                'default_size' => ['w' => 8, 'h' => 4],
                'min_size' => ['w' => 8, 'h' => 4],
                'default_title' => 'Inventory GUI',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'inventory',
            ],
            'profile_skills_widget' => [
                'type' => 'profile_skills_widget',
                'name' => 'Skills',
                'description' => 'Skill levels and XP progress from the selected SkyBlock profile.',
                'default_size' => ['w' => 6, 'h' => 5],
                'min_size' => ['w' => 5, 'h' => 4],
                'default_title' => 'Skills',
                'default_settings' => [
                    'username' => '',
                    'skill_selected_keys' => [],
                ],
                'preview' => 'skills',
            ],
            'profile_slayers_widget' => [
                'type' => 'profile_slayers_widget',
                'name' => 'Slayers',
                'description' => 'Slayer bosses, levels, and XP progress (pick which bosses to show).',
                'default_size' => ['w' => 8, 'h' => 4],
                'min_size' => ['w' => 6, 'h' => 3],
                'default_title' => 'Slayers',
                'default_settings' => [
                    'username' => '',
                    'slayer_selected_keys' => [],
                ],
                'preview' => 'slayers',
            ],
            'profile_collections_widget' => [
                'type' => 'profile_collections_widget',
                'name' => 'Collections',
                'description' => 'SkyBlock collection progress for items you pick.',
                'default_size' => ['w' => 6, 'h' => 5],
                'min_size' => ['w' => 5, 'h' => 3],
                'default_title' => 'Collections',
                'default_settings' => [
                    'username' => '',
                    'collection_selected_keys' => [],
                ],
                'preview' => 'collections',
            ],
            'profile_networth_widget' => [
                'type' => 'profile_networth_widget',
                'name' => 'Net worth',
                'description' => 'Purse, bank, and total net worth.',
                'default_size' => ['w' => 6, 'h' => 3],
                'min_size' => ['w' => 6, 'h' => 3],
                'default_title' => 'Net worth',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'networth',
            ],
            'profile_pets_widget' => [
                'type' => 'profile_pets_widget',
                'name' => 'Pets',
                'description' => 'Top pets by rarity (unique list).',
                'default_size' => ['w' => 6, 'h' => 5],
                'min_size' => ['w' => 6, 'h' => 5],
                'default_title' => 'Pets',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'pets',
            ],
            'profile_equipment_widget' => [
                'type' => 'profile_equipment_widget',
                'name' => 'Equipment',
                'description' => 'Equipment slots (vertical list).',
                'default_size' => ['w' => 1, 'h' => 4],
                'min_size' => ['w' => 1, 'h' => 4],
                'default_title' => 'Equipment',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'equipment',
            ],
            'profile_armor_widget' => [
                'type' => 'profile_armor_widget',
                'name' => 'Armor',
                'description' => 'Armor pieces (vertical list).',
                'default_size' => ['w' => 1, 'h' => 4],
                'min_size' => ['w' => 1, 'h' => 4],
                'default_title' => 'Armor',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'armor',
            ],
            'profile_weapons_widget' => [
                'type' => 'profile_weapons_widget',
                'name' => 'Weapons',
                'description' => 'Detected weapons from the player inventory.',
                'default_size' => ['w' => 4, 'h' => 1],
                'min_size' => ['w' => 4, 'h' => 1],
                'default_title' => 'Weapons',
                'default_settings' => [
                    'username' => '',
                ],
                'preview' => 'weapons',
            ],
            'event_timers_widget' => [
                'type' => 'event_timers_widget',
                'name' => 'Event timer',
                'description' => 'One rotating SkyBlock event (same logic as Event Timer page).',
                'default_size' => ['w' => 5, 'h' => 4],
                'min_size' => ['w' => 4, 'h' => 3],
                'default_title' => 'Event timer',
                'default_settings' => [
                    'event_timer_key' => 'dark-auction',
                ],
                'preview' => 'events',
            ],
            'skyblock_calendar_widget' => [
                'type' => 'skyblock_calendar_widget',
                'name' => 'SkyBlock calendar',
                'description' => 'Current SkyBlock date and notable events today.',
                'default_size' => ['w' => 6, 'h' => 5],
                'min_size' => ['w' => 5, 'h' => 4],
                'default_title' => 'SkyBlock calendar',
                'default_settings' => [],
                'preview' => 'calendar',
            ],
            'mayor_status_widget' => [
                'type' => 'mayor_status_widget',
                'name' => 'Mayor',
                'description' => 'Current mayor and perks (from Hypixel election API).',
                'default_size' => ['w' => 3, 'h' => 2],
                'min_size' => ['w' => 3, 'h' => 2],
                'default_title' => 'Mayor',
                'default_settings' => [],
                'preview' => 'mayor',
            ],
            'bazaar_top_widget' => [
                'type' => 'bazaar_top_widget',
                'name' => 'Bazaar margins',
                'description' => 'Top items by instant-sell vs instant-buy margin.',
                'default_size' => ['w' => 7, 'h' => 6],
                'min_size' => ['w' => 6, 'h' => 5],
                'default_title' => 'Bazaar margins',
                'default_settings' => [
                    'limit' => 8,
                ],
                'preview' => 'bazaar',
            ],
            'leaderboard_rank_widget' => [
                'type' => 'leaderboard_rank_widget',
                'name' => 'Leaderboard rank',
                'description' => 'Global rank for a player on the selected leaderboard sort.',
                'default_size' => ['w' => 3, 'h' => 2],
                'min_size' => ['w' => 3, 'h' => 2],
                'default_title' => 'Leaderboard rank',
                'default_settings' => [
                    'username' => '',
                    'sort' => 'level',
                ],
                'preview' => 'leaderboard',
            ],
        ];
    }
}
