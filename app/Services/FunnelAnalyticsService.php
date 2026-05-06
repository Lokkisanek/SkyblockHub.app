<?php

namespace App\Services;

use App\Models\FunnelEvent;
use Illuminate\Http\Request;

class FunnelAnalyticsService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function trackFromRequest(Request $request, string $eventName, array $properties = []): void
    {
        $properties = $this->enrichProperties($request, $properties);

        $path = null;
        $contextPath = $request->input('context.path');

        if (is_string($contextPath) && $contextPath !== '') {
            $path = mb_substr($contextPath, 0, 255);
        } elseif (is_string($request->path())) {
            $path = mb_substr('/'.ltrim($request->path(), '/'), 0, 255);
        }

        $sessionId = null;
        if ($request->hasSession()) {
            $sessionId = $request->session()->getId();
        }

        $this->track(
            eventName: $eventName,
            properties: $properties,
            userId: $request->user()?->id,
            sessionId: $sessionId,
            path: $path,
            referrer: $request->headers->get('referer')
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(
        string $eventName,
        array $properties = [],
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $path = null,
        ?string $referrer = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->isAllowedEvent($eventName)) {
            return;
        }

        FunnelEvent::query()->create([
            'event_name' => $eventName,
            'user_id' => $userId,
            'session_id' => $sessionId ? mb_substr($sessionId, 0, 128) : null,
            'path' => $path ? mb_substr($path, 0, 255) : null,
            'referrer' => $referrer ? mb_substr($referrer, 0, 1024) : null,
            'properties' => $this->sanitizeProperties($properties),
            'occurred_at' => now(),
        ]);
    }

    public function isAllowedEvent(string $eventName): bool
    {
        return in_array($eventName, (array) config('analytics.allowed_events', []), true);
    }

    private function isEnabled(): bool
    {
        return (bool) config('analytics.funnel_enabled', true);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $normalized = $this->normalizeValue($value);
            if ($normalized !== null) {
                $sanitized[$key] = $normalized;
            }
        }

        return $sanitized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 512);
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $k => $nested) {
                if (! is_string($k) && ! is_int($k)) {
                    continue;
                }

                $normalized = $this->normalizeValue($nested);
                if ($normalized !== null) {
                    $result[$k] = $normalized;
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function enrichProperties(Request $request, array $properties): array
    {
        $referrer = $request->headers->get('referer');
        $utm = $this->extractUtm($request, $referrer);

        foreach ($utm as $key => $value) {
            if (! array_key_exists($key, $properties) && $value !== null) {
                $properties[$key] = $value;
            }
        }

        if (! isset($properties['source']) || ! is_string($properties['source']) || trim($properties['source']) === '') {
            $properties['source'] = $this->inferSource($request, $referrer, $utm['utm_source'] ?? null);
        }

        return $properties;
    }

    /**
     * @return array<string, string|null>
     */
    private function extractUtm(Request $request, ?string $referrer): array
    {
        $utm = [
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
        ];

        if ($this->hasUtmValues($utm)) {
            return $utm;
        }

        if (! $referrer) {
            return $utm;
        }

        $parts = parse_url($referrer);
        if (empty($parts['query'])) {
            return $utm;
        }

        parse_str($parts['query'], $query);

        return [
            'utm_source' => $query['utm_source'] ?? null,
            'utm_medium' => $query['utm_medium'] ?? null,
            'utm_campaign' => $query['utm_campaign'] ?? null,
        ];
    }

    /**
     * @param array<string, string|null> $utm
     */
    private function hasUtmValues(array $utm): bool
    {
        foreach ($utm as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function inferSource(Request $request, ?string $referrer, ?string $utmSource): string
    {
        if (is_string($utmSource) && trim($utmSource) !== '') {
            return $this->normalizeSourceLabel($utmSource);
        }

        if (! $referrer) {
            return 'direct';
        }

        $host = strtolower((string) (parse_url($referrer, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return 'direct';
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && str_contains($host, $appHost)) {
            return 'internal';
        }

        $map = [
            'discord.com' => 'discord',
            'discord.gg' => 'discord',
            't.co' => 'twitter',
            'twitter.com' => 'twitter',
            'x.com' => 'twitter',
            'reddit.com' => 'reddit',
            'youtube.com' => 'youtube',
            'youtu.be' => 'youtube',
            'google.' => 'google',
            'bing.com' => 'bing',
            'hypixel.net' => 'hypixel',
        ];

        foreach ($map as $needle => $source) {
            if (str_contains($host, $needle)) {
                return $source;
            }
        }

        return $this->normalizeSourceLabel($host);
    }

    private function normalizeSourceLabel(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'unknown';
    }
}
