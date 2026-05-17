<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuideContentNormalizer
{
    /** @var list<string> */
    private const BLOCK_TYPES = ['paragraph', 'callout', 'list', 'table', 'links', 'citation'];

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(mixed $sections): array
    {
        if (! is_array($sections)) {
            throw ValidationException::withMessages(['sections' => 'Guide sections must be an array.']);
        }

        $normalized = [];

        foreach (array_values($sections) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = $this->cleanText($section['heading'] ?? '', 120);
            if ($heading === '') {
                continue;
            }

            $id = $this->cleanSlug($section['id'] ?? Str::slug($heading));
            $blocks = $this->blocks($section['blocks'] ?? []);

            if ($blocks === []) {
                continue;
            }

            $normalized[] = [
                'id' => $id !== '' ? $id : 'section-'.($index + 1),
                'heading' => $heading,
                'level' => (int) (($section['level'] ?? 2) === 3 ? 3 : 2),
                'blocks' => $blocks,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['sections' => 'Add at least one section with content.']);
        }

        return $normalized;
    }

    /**
     * @return list<array{label: string, url: string, external: bool}>
     */
    public function usefulLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($link) {
            if (! is_array($link)) {
                return null;
            }

            $label = $this->cleanText($link['label'] ?? '', 120);
            $url = $this->cleanUrl($link['url'] ?? '');

            if ($label === '' || $url === '' || $this->isBlockedGuideLink($label, $url)) {
                return null;
            }

            return [
                'label' => $label,
                'url' => $url,
                'external' => (bool) ($link['external'] ?? true),
            ];
        }, $links)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($blocks) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            if (! in_array($type, self::BLOCK_TYPES, true)) {
                continue;
            }

            $next = match ($type) {
                'paragraph' => $this->paragraphBlock($block),
                'callout' => $this->calloutBlock($block),
                'list' => $this->listBlock($block),
                'table' => $this->tableBlock($block),
                'links' => $this->linksBlock($block),
                'citation' => $this->citationBlock($block),
            };

            if ($next !== null) {
                $normalized[] = $next;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function paragraphBlock(array $block): ?array
    {
        $text = $this->cleanText($block['text'] ?? '', 2000);

        return $text === '' ? null : ['type' => 'paragraph', 'text' => $text];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function calloutBlock(array $block): ?array
    {
        $title = $this->cleanText($block['title'] ?? '', 120);
        $text = $this->cleanText($block['text'] ?? '', 1000);
        $variant = in_array(($block['variant'] ?? 'info'), ['info', 'warning', 'success'], true)
            ? (string) ($block['variant'] ?? 'info')
            : 'info';

        if ($title === '' && $text === '') {
            return null;
        }

        return ['type' => 'callout', 'title' => $title, 'text' => $text, 'variant' => $variant];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function listBlock(array $block): ?array
    {
        $items = $this->stringList($block['items'] ?? [], 500);

        return $items === [] ? null : ['type' => 'list', 'items' => $items, 'ordered' => (bool) ($block['ordered'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function tableBlock(array $block): ?array
    {
        $headers = $this->stringList($block['headers'] ?? [], 80);
        if ($headers === []) {
            return null;
        }

        $rows = [];
        foreach (array_values((array) ($block['rows'] ?? [])) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = $this->stringList($row, 300);
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows === [] ? null : ['type' => 'table', 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function linksBlock(array $block): ?array
    {
        $items = $this->usefulLinks($block['items'] ?? []);

        return $items === [] ? null : ['type' => 'links', 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function citationBlock(array $block): ?array
    {
        $text = $this->cleanText($block['text'] ?? '', 1200);
        $source = $this->cleanText($block['source'] ?? '', 160);
        $url = $this->cleanUrl($block['url'] ?? '');

        if ($text === '' && $source === '' && $url === '') {
            return null;
        }

        return [
            'type' => 'citation',
            'text' => $text,
            'source' => $source,
            'url' => $url,
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => $this->cleanText($item, $limit),
            $value
        ), fn ($item) => $item !== ''));
    }

    private function cleanSlug(mixed $value): string
    {
        return Str::slug($this->cleanText($value, 100));
    }

    private function cleanText(mixed $value, int $limit): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? ''), $limit, '');
    }

    private function cleanUrl(mixed $value): string
    {
        $url = trim((string) $value);

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private function isBlockedGuideLink(string $label, string $url): bool
    {
        $haystack = strtolower($label.' '.$url);

        return str_contains($haystack, 'skycrypt')
            || str_contains($haystack, 'sky.shiiyu.moe')
            || str_contains($haystack, 'cofl')
            || str_contains($haystack, 'coflnet')
            || str_contains($haystack, 'sky.coflnet.com');
    }
}
