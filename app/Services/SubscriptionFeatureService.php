<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\TrialRedemption;
use Carbon\CarbonImmutable;

class SubscriptionFeatureService
{
    public const TIER_FREE = 'free';
    public const TIER_VIP = 'vip';
    public const TIER_MVP = 'mvp';

    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user): array
    {
        $entitlement = $user?->entitlement;
        $isUsableEntitlement = $this->isUsableEntitlement($entitlement);

        $tier = $isUsableEntitlement
            ? $this->normalizeTier((string) ($entitlement?->tier ?? self::TIER_FREE))
            : self::TIER_FREE;

        // Backward compatibility: legacy active records may only have unlocked slot count.
        if (
            $isUsableEntitlement
            && $tier === self::TIER_FREE
            && (int) ($entitlement?->dashboard_slots_unlocked ?? 1) > 1
        ) {
            $tier = self::TIER_VIP;
        }

        $isVipOrMvp = in_array($tier, [self::TIER_VIP, self::TIER_MVP], true);
        $isMvp = $tier === self::TIER_MVP;
        $slotUnlock = $isVipOrMvp
            ? max(3, (int) ($entitlement?->dashboard_slots_unlocked ?? 3))
            : 1;

        $trialEligible = false;
        if ($user && $user->discord_id) {
            $alreadyRedeemed = TrialRedemption::query()
                ->where('discord_id', $user->discord_id)
                ->exists();

            $trialEligible = ! $alreadyRedeemed;
        }

        return [
            'tier' => $tier,
            'status' => $isUsableEntitlement ? (string) $entitlement->status : 'inactive',
            'has_active_entitlement' => $isUsableEntitlement,
            'is_trialing' => $isUsableEntitlement && $entitlement?->status === 'trialing',
            'trial_eligible' => $trialEligible,
            'requires_discord_for_paid' => true,
            'dashboard_slots_unlocked' => $slotUnlock,
            'top_flips_limit' => $isVipOrMvp ? 3 : 1,
            'refresh_seconds' => $isMvp ? 60 : ($isVipOrMvp ? 120 : 180),
            'priority_widget_updates' => $isVipOrMvp,
            'can_ai_flips' => $isMvp,
            'leaderboard_tag' => $tier === self::TIER_MVP ? 'MVP' : ($tier === self::TIER_VIP ? 'VIP' : null),
            'free_features' => [
                'web_alerts' => true,
                'discord_alerts' => true,
                'flip_filters' => true,
            ],
        ];
    }

    public function isUsableEntitlement(?UserEntitlement $entitlement): bool
    {
        if (! $entitlement) {
            return false;
        }

        if (! in_array($entitlement->status, ['active', 'trialing'], true)) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($entitlement->status === 'trialing') {
            if (! $entitlement->trial_ends_at) {
                return false;
            }

            return CarbonImmutable::parse($entitlement->trial_ends_at)->greaterThan($now);
        }

        if ($entitlement->current_period_ends_at) {
            return CarbonImmutable::parse($entitlement->current_period_ends_at)->greaterThan($now);
        }

        return true;
    }

    public function normalizeTier(string $tier): string
    {
        return in_array($tier, [self::TIER_VIP, self::TIER_MVP], true) ? $tier : self::TIER_FREE;
    }
}
