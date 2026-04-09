<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEntitlement;

class DashboardEntitlementService
{
    public function getDashboardLimits(?User $user): array
    {
        $totalSlots = 3;
        $unlockedSlots = 1;
        $entitlement = null;

        if ($user) {
            $entitlement = UserEntitlement::query()->where('user_id', $user->id)->first();

            if ($entitlement && $entitlement->status === 'active') {
                $unlockedSlots = max(1, min($totalSlots, (int) $entitlement->dashboard_slots_unlocked));
            }
        }

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
            'has_active_entitlement' => (bool) ($entitlement && $entitlement->status === 'active'),
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
