<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistWatchlist extends Model
{
    public const MAX_FOLLOWED_ARTISTS = 30;

    protected $fillable = [
        'user_id',
        'artist_id',
        'artist_name',
        'artist_image_url',
        'followers',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'followers' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

