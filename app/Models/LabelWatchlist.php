<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelWatchlist extends Model
{
    public const MAX_FOLLOWED_LABELS = 30;

    protected $fillable = [
        'user_id',
        'label',
        'normalized_label',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
