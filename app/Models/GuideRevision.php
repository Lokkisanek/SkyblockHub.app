<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideRevision extends Model
{
    protected $fillable = [
        'guide_id',
        'guide_submission_id',
        'user_id',
        'title',
        'slug',
        'description',
        'category',
        'category_label',
        'sections',
        'useful_links',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'useful_links' => 'array',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GuideSubmission::class, 'guide_submission_id');
    }
}
