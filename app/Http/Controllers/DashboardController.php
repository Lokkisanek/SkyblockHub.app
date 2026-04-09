<?php

namespace App\Http\Controllers;

use App\Models\BazaarProduct;
use App\Models\UserDashboard;
use App\Services\DashboardEntitlementService;
use App\Services\MayorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    ];

    public function __construct(
        private readonly DashboardEntitlementService $entitlementService,
        private readonly MayorService $mayorService,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $slotIndex = max((int) $request->query('slot', 1), 1);
        $limits = $this->entitlementService->getDashboardLimits($user);

        if (! $this->entitlementService->canAccessSlot($user, $slotIndex)) {
            $slotIndex = 1;
        }

        $dashboard = null;
        $liveWidgetData = [
            'items' => [],
            'event' => null,
        ];

        if ($user) {
            $dashboard = UserDashboard::query()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'slot_index' => $slotIndex],
                    [
                        'name' => $slotIndex === 1 ? 'Main Dashboard' : "Dashboard Slot {$slotIndex}",
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

            $liveWidgetData = $this->buildLiveWidgetData($dashboard);
        }

        return Inertia::render('Dashboard', [
            'canEditDashboard' => (bool) ($user && $user->is_mc_linked),
            'requiresLogin' => ! $user,
            'requiresMinecraftLink' => (bool) ($user && ! $user->is_mc_linked),
            'activeSlotIndex' => $slotIndex,
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
            'dashboardLimits' => $limits,
            'liveWidgetData' => $liveWidgetData,
            'widgetTemplates' => $this->widgetTemplates(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $slotIndex = max((int) $request->input('slot_index', 1), 1);

        if (! $user || ! $user->is_mc_linked) {
            return back()->withErrors([
                'dashboard' => 'Dashboard can be edited only after Minecraft account linking.',
            ]);
        }

        if (! $this->entitlementService->canAccessSlot($user, $slotIndex)) {
            return back()->withErrors([
                'dashboard' => 'This dashboard slot is locked by Stripe entitlement.',
            ]);
        }

        $data = $request->validate([
            'slot_index' => ['required', 'integer', 'min:1', 'max:3'],
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
                    'name' => $slotIndex === 1 ? 'Main Dashboard' : "Dashboard Slot {$slotIndex}",
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
                'default_size' => ['w' => 3, 'h' => 5],
                'min_size' => ['w' => 3, 'h' => 5],
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
                'default_size' => ['w' => 8, 'h' => 6],
                'min_size' => ['w' => 8, 'h' => 6],
                'default_title' => 'Inventory GUI',
                'default_settings' => [
                    'username' => '',
                    'show_hotbar' => false,
                ],
                'preview' => 'inventory',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveWidgetData(UserDashboard $dashboard): array
    {
        $itemNames = $dashboard->widgets
            ->where('type', 'item_flip_watcher')
            ->map(fn ($widget) => trim((string) ($widget->settings['item_name'] ?? '')))
            ->filter()
            ->map(fn (string $name) => Str::lower($name))
            ->unique()
            ->values();

        $items = [];

        if ($itemNames->isNotEmpty()) {
            $rows = BazaarProduct::query()
                ->join('bazaar_prices', 'bazaar_products.product_id', '=', 'bazaar_prices.product_id')
                ->select([
                    'bazaar_products.name',
                    'bazaar_products.product_id',
                    'bazaar_prices.buy_price',
                    'bazaar_prices.sell_price',
                    'bazaar_prices.buy_moving_week',
                    'bazaar_prices.sell_moving_week',
                    'bazaar_prices.updated_at',
                ])
                ->whereIn(DB::raw('LOWER(bazaar_products.name)'), $itemNames->all())
                ->get();

            $items = $rows->mapWithKeys(function ($row) {
                $buy = (float) $row->buy_price;
                $sell = (float) $row->sell_price;
                $margin = ($buy * 0.9875) - $sell;

                return [
                    Str::lower((string) $row->name) => [
                        'name' => $row->name,
                        'product_id' => $row->product_id,
                        'buy_price' => $buy,
                        'sell_price' => $sell,
                        'margin' => $margin,
                        'buy_moving_week' => (float) $row->buy_moving_week,
                        'sell_moving_week' => (float) $row->sell_moving_week,
                        'updated_at' => optional($row->updated_at)?->toIso8601String(),
                    ],
                ];
            })->all();
        }

        $mayor = $this->mayorService->getCurrentMayorData();

        return [
            'items' => $items,
            'event' => [
                'mayor_name' => $mayor['name'] ?? 'Unknown',
                'election' => $mayor['election'] ?? null,
                'last_updated' => $mayor['last_updated'] ?? null,
            ],
        ];
    }
}
