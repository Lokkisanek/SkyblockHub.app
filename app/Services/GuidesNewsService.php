<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GuidesNewsService
{
    private const FEED_URL = 'https://hypixel.net/forums/skyblock-patch-notes.158/index.rss';

    private const CACHE_KEY = 'guides:patch-notes-rss';

    private const CACHE_TTL = 3600;

    /**
     * @return list<array{title: string, url: string, date: string, author: string, replies: int|null, preview: string}>
     */
    public function getRecentPatches(int $limit = 20): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($limit) {
            return $this->fetchFromRss($limit);
        });
    }

    /**
     * @return list<array{title: string, url: string, date: string, author: string, replies: int|null, preview: string}>
     */
    private function fetchFromRss(int $limit): array
    {
        try {
            $response = Http::timeout(12)->get(self::FEED_URL);

            if (! $response->successful()) {
                return [];
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false || ! isset($xml->channel->item)) {
                return [];
            }

            $items = [];
            $count = 0;

            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) {
                    break;
                }

                $title = trim((string) ($item->title ?? ''));
                $link = trim((string) ($item->link ?? ''));

                if ($title === '' || $link === '') {
                    continue;
                }

                $pubDate = trim((string) ($item->pubDate ?? ''));
                $description = strip_tags((string) ($item->description ?? ''));
                $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $preview = mb_substr(preg_replace('/\s+/', ' ', $description) ?? '', 0, 220);

                $author = 'Hypixel Team';
                if (isset($item->children('dc', true)->creator)) {
                    $author = trim((string) $item->children('dc', true)->creator) ?: $author;
                }

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'date' => $pubDate !== '' ? date('M j, Y', strtotime($pubDate) ?: time()) : '',
                    'author' => $author,
                    'replies' => null,
                    'preview' => $preview,
                ];

                $count++;
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('guides.patch_rss_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
