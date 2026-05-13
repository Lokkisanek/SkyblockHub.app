<?php

namespace App\Services;

use App\Models\User;

class DashboardEntitlementService
{
    public function getDashboardLimits(?User $user): array
    {
        $totalSlots = 3;
        $unlockedSlots = $totalSlots;
        $lockedSlots = [];
        return [
            'total_slots' => $totalSlots,
            'free_slots' => $totalSlots,
            'unlocked_slots' => $unlockedSlots,
            'locked_slots' => $lockedSlots,
            'paywall_provider' => 'none',
            'has_active_entitlement' => false,
        ];
    }

    public function canAccessSlot(?User $user, int $slotIndex): bool
    {
        if ($slotIndex < 1) {
            return false;
        }

        return $slotIndex <= 3;
    }
}
