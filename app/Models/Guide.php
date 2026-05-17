<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guide extends Model
{
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category',
        'category_label',
        'sort_order',
        'status',
        'last_updated_on',
        'sections',
        'useful_links',
        'created_by',
        'updated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'useful_links' => 'array',
            'last_updated_on' => 'date:Y-m-d',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(GuideSubmission::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GuideRevision::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'categoryLabel' => $this->category_label,
            'lastUpdated' => today()->toDateString(),
            'sections' => $this->filteredSections($this->sections ?? []),
            'usefulLinks' => $this->filteredLinks($this->useful_links ?? []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function filteredSections(array $sections): array
    {
        return array_values(array_map(function (array $section) {
            $section['blocks'] = array_values(array_filter(array_map(function ($block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'links') {
                    return $block;
                }

                $block['items'] = $this->filteredLinks($block['items'] ?? []);

                return $block['items'] === [] ? null : $block;
            }, $section['blocks'] ?? [])));

            return $section;
        }, $sections));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filteredLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter($links, function ($link) {
            if (! is_array($link)) {
                return false;
            }

            $haystack = strtolower(($link['label'] ?? '').' '.($link['url'] ?? ''));

            return ! str_contains($haystack, 'skycrypt')
                && ! str_contains($haystack, 'sky.shiiyu.moe')
                && ! str_contains($haystack, 'cofl')
                && ! str_contains($haystack, 'coflnet')
                && ! str_contains($haystack, 'sky.coflnet.com');
        }));
    }
}
