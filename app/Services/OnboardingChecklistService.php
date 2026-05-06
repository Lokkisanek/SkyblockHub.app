<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserOnboarding;
use App\Services\SubscriptionFeatureService;
use Illuminate\Http\Request;

class OnboardingChecklistService
{
    private const COPY_VARIANTS = ['a', 'b'];

    /**
     * @var array<int, array{key: string, route: string}>
     */
    private const STEP_DEFINITIONS = [
        'link_minecraft' => 'profile.edit',
        'open_dashboard' => 'dashboard',
        'explore_module' => 'bazaar',
        'visit_billing' => 'billing',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SEGMENT_STEPS = [
        'unlinked' => ['link_minecraft', 'open_dashboard', 'explore_module', 'visit_billing'],
        'linked_free' => ['open_dashboard', 'explore_module', 'visit_billing'],
        'trial' => ['open_dashboard', 'explore_module', 'visit_billing'],
        'paid' => ['open_dashboard', 'explore_module', 'visit_billing'],
    ];

    /**
     * @var array<string, string>
     */
    private const ROUTE_STEP_MAP = [
        'dashboard' => 'open_dashboard',
        'bazaar' => 'explore_module',
        'npc-flips' => 'explore_module',
        'profile-stats' => 'explore_module',
        'event-timer' => 'explore_module',
        'mayors' => 'explore_module',
        'leaderboards' => 'explore_module',
        'billing' => 'visit_billing',
        'billing.success' => 'visit_billing',
    ];

    public function __construct(
        private readonly SubscriptionFeatureService $subscriptionFeatureService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function captureAndGetState(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $routeName = (string) optional($request->route())->getName();
        if ($routeName !== '' && isset(self::ROUTE_STEP_MAP[$routeName])) {
            $this->markStep($user, self::ROUTE_STEP_MAP[$routeName]);
        }

        if ((bool) $user->is_mc_linked) {
            $this->markStep($user, 'link_minecraft');
        }

        $features = $this->subscriptionFeatureService->forUser($user);

        return $this->getState($user, $features);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $subscriptionFeatures
     * @return array<string, mixed>
     */
    public function getState(User $user, array $subscriptionFeatures = []): array
    {
        $onboarding = $this->getOrCreate($user);
        $completedSteps = $this->completedSteps($onboarding);
        $segment = $this->resolveSegment($user, $subscriptionFeatures);
        $stepKeys = $this->resolveStepsForSegment($segment);
        $copyVariant = $this->resolveCopyVariant($onboarding, $user);

        $steps = array_map(function (string $stepKey) use ($completedSteps): array {
            return [
                'key' => $stepKey,
                'routeName' => self::STEP_DEFINITIONS[$stepKey] ?? null,
                'completed' => in_array($stepKey, $completedSteps, true),
            ];
        }, $stepKeys);

        $totalCount = count($steps);
        $completedCount = count(array_filter($steps, fn (array $step): bool => $step['completed']));
        $progressPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0.0;
        $isComplete = $completedCount >= $totalCount;

        if ($isComplete && ! $onboarding->completed_at) {
            $onboarding->completed_at = now();
            $onboarding->save();
        }

        return [
            'show' => ! $isComplete && $onboarding->dismissed_at === null,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'progressPct' => $progressPct,
            'steps' => $steps,
            'segment' => $segment,
            'copyVariant' => $copyVariant,
        ];
    }

    public function markStep(User $user, string $step): void
    {
        if (! $this->isKnownStep($step)) {
            return;
        }

        $onboarding = $this->getOrCreate($user);
        $completed = $this->completedSteps($onboarding);

        if (in_array($step, $completed, true)) {
            return;
        }

        $completed[] = $step;
        $onboarding->completed_steps = array_values(array_unique($completed));
        $onboarding->dismissed_at = null;
        $onboarding->save();
    }

    public function dismiss(User $user): void
    {
        $onboarding = $this->getOrCreate($user);
        $onboarding->dismissed_at = now();
        $onboarding->save();
    }

    private function isKnownStep(string $step): bool
    {
        return array_key_exists($step, self::STEP_DEFINITIONS);
    }

    private function getOrCreate(User $user): UserOnboarding
    {
        return UserOnboarding::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['completed_steps' => []]
        );
    }

    /**
     * @return array<int, string>
     */
    private function completedSteps(UserOnboarding $onboarding): array
    {
        $steps = $onboarding->completed_steps;

        if (! is_array($steps)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => is_string($value) ? $value : '',
            $steps
        )));
    }

    /**
     * @param array<string, mixed> $subscriptionFeatures
     */
    private function resolveSegment(User $user, array $subscriptionFeatures): string
    {
        if (! $user->is_mc_linked) {
            return 'unlinked';
        }

        if (! empty($subscriptionFeatures['is_trialing'])) {
            return 'trial';
        }

        if (! empty($subscriptionFeatures['has_active_entitlement'])) {
            return 'paid';
        }

        return 'linked_free';
    }

    /**
     * @return array<int, string>
     */
    private function resolveStepsForSegment(string $segment): array
    {
        return self::SEGMENT_STEPS[$segment] ?? self::SEGMENT_STEPS['linked_free'];
    }

    private function resolveCopyVariant(UserOnboarding $onboarding, User $user): string
    {
        $current = is_string($onboarding->copy_variant) ? $onboarding->copy_variant : '';

        if (in_array($current, self::COPY_VARIANTS, true)) {
            return $current;
        }

        $variant = ($user->id % 2 === 0) ? 'b' : 'a';
        $onboarding->copy_variant = $variant;
        $onboarding->save();

        return $variant;
    }
}
