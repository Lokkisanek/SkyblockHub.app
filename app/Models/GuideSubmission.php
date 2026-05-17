<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideSubmission extends Model
{
    public const TYPE_NEW_ARTICLE = 'new_article';

    public const TYPE_EDIT = 'edit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'guide_id',
        'user_id',
        'submitter_name',
        'submitter_contact',
        'title',
        'slug',
        'description',
        'category',
        'category_label',
        'sections',
        'useful_links',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'useful_links' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }
}
