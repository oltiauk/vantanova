<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistWatchlistSearch extends Model
{
    protected $fillable = [
        'user_id',
        'results',
        'last_executed_at',
        'expires_at',
        'artist_count',
        'track_count',
    ];

    protected $casts = [
        'results' => 'array',
        'last_executed_at' => 'datetime',
        'expires_at' => 'datetime',
        'artist_count' => 'integer',
        'track_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

