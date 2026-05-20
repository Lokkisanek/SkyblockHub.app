<?php

namespace App\Services;

use Illuminate\Http\Request;

class SeoService
{
    public static function getPageSeo(Request $request): array
    {
        $path = $request->path();
        $defaultTitle = 'SkyblockHub - Hypixel SkyBlock Intelligence';
        $defaultDescription = 'SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, crafting opportunities, profile stats, mayor tracking, and event timers.';

        $seoData = [
            'title' => $defaultTitle,
            'description' => $defaultDescription,
            'ogTitle' => $defaultTitle,
            'ogDescription' => $defaultDescription,
            'ogImage' => asset('img/logo-white.webp'),
        ];

        // Page-specific SEO
        if ($path === 'dashboard') {
            $seoData['title'] = 'Dashboard - SkyblockHub';
            $seoData['description'] = 'Track your SkyBlock profile, monitor active perks, and access all SkyblockHub tools from your personalized dashboard.';
        } elseif ($path === 'bazaar') {
            $seoData['title'] = 'Bazaar Flipping - SkyblockHub';
            $seoData['description'] = 'Find profitable Bazaar flips with advanced filtering. Analyze price trends, identify instabuys/instasells, and maximize your coins/hour.';
        } elseif ($path === 'bazaar/npc') {
            $seoData['title'] = 'NPC Flips - SkyblockHub';
            $seoData['description'] = 'Discover NPC arbitrage opportunities. Buy from Bazaar, sell to NPCs, or vice versa. Instantly identify profit margins.';
        } elseif ($path === 'profile') {
            $seoData['title'] = 'Profile Stats - SkyblockHub';
            $seoData['description'] = 'View detailed SkyBlock profile statistics, item valuations, and skill progression for any player.';
        } elseif ($path === 'event-timer') {
            $seoData['title'] = 'Event Timer - SkyblockHub';
            $seoData['description'] = 'Real-time countdown timers for SkyBlock special events, mayor elections, and seasonal activities.';
        } elseif ($path === 'leaderboards') {
            $seoData['title'] = 'Leaderboards - SkyblockHub';
            $seoData['description'] = 'Compete with other players. View leaderboards for skills, net worth, wealth, and various SkyBlock achievements.';
        } elseif ($path === 'trust-index') {
            $seoData['title'] = 'Scammer List - SkyblockHub';
            $seoData['description'] = 'Search Hypixel SkyBlock players against the community scammer list and learn common trade, co-op, and Discord scams.';
        } elseif ($path === 'trust-index/report') {
            $seoData['title'] = 'Report a Scam - SkyblockHub';
            $seoData['description'] = 'Submit a scam report with evidence for review on the SkyblockHub community scammer list.';
        } elseif ($path === 'trust-index/appeal') {
            $seoData['title'] = 'Appeal Listing - SkyblockHub';
            $seoData['description'] = 'Appeal a wrongful entry on the SkyblockHub community scammer list.';
        }

        return $seoData;
    }
}
