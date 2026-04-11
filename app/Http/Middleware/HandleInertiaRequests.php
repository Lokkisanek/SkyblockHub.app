<?php

namespace App\Http\Middleware;

use App\Services\MayorService;
use App\Services\PerkService;
use App\Support\TestingAdminGate;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'testing_admin' => TestingAdminGate::allows($request->user()),
            ],
            'navigation' => [
                'experimental_modules' => [
                    'crafting' => (bool) config('navigation.experimental_modules.crafting', false),
                    'dungeon_party' => (bool) config('navigation.experimental_modules.dungeon_party', false),
                    'portfolio' => (bool) config('navigation.experimental_modules.portfolio', false),
                    'bin_sniper' => (bool) config('navigation.experimental_modules.bin_sniper', false),
                ],
            ],
            'currentMayor' => fn () => $this->buildCurrentMayorWidget(),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrentMayorWidget(): array
    {
        $mayor = app(MayorService::class)->getCurrentMayorData();
        $perkState = app(PerkService::class)->buildState($mayor);
        $mayorPerks = array_values((array) ($mayor['perks'] ?? []));

        $activePerkCount = count(array_filter($mayorPerks, function ($perk) {
            if (! is_array($perk)) {
                return false;
            }

            $name = trim((string) ($perk['name'] ?? ''));
            $description = trim((string) ($perk['description'] ?? ''));

            return $name !== '' || $description !== '';
        }));

        return [
            'name' => $mayor['name'] ?? 'Unknown',
            'last_updated' => $mayor['last_updated'] ?? null,
            'perks' => $mayorPerks,
            'active_perk_count' => $activePerkCount,
            'recognized_active_perks' => count(array_filter((array) ($perkState['active_perks'] ?? []))),
        ];
    }
}
