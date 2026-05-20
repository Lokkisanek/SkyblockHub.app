<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustIndexSubmission extends Model
{
    public const TYPE_REPORT = 'report';

    public const TYPE_APPEAL = 'appeal';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'status',
        'minecraft_username',
        'submitter_name',
        'submitter_contact',
        'category',
        'description',
        'evidence',
        'user_id',
        'reviewed_at',
        'reviewed_by',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
