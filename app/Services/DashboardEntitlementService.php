<?php

namespace App\Services;

use App\Models\User;

class DashboardEntitlementService
{
    public function __construct(
        private readonly SubscriptionFeatureService $subscriptionFeatureService,
    ) {
    }

    public function getDashboardLimits(?User $user): array
    {
        $totalSlots = 3;
        $features = $this->subscriptionFeatureService->forUser($user);
        $unlockedSlots = max(1, min($totalSlots, (int) ($features['dashboard_slots_unlocked'] ?? 1)));

        $lockedSlots = [];
        for ($slot = 1; $slot <= $totalSlots; $slot++) {
            if ($slot > $unlockedSlots) {
                $lockedSlots[] = $slot;
            }
        }

        return [
            'total_slots' => $totalSlots,
            'free_slots' => 1,
            'unlocked_slots' => $unlockedSlots,
            'locked_slots' => $lockedSlots,
            'paywall_provider' => 'stripe',
            'has_active_entitlement' => (bool) ($features['has_active_entitlement'] ?? false),
        ];
    }

    public function canAccessSlot(?User $user, int $slotIndex): bool
    {
        if ($slotIndex < 1) {
            return false;
        }

        $limits = $this->getDashboardLimits($user);

        return $slotIndex <= (int) $limits['unlocked_slots'];
    }
}
