<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlacklistedArtist extends Model
{
    protected $fillable = [
        'user_id',
        'spotify_artist_id',
        'artist_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if an artist is blacklisted for a user
     */
    public static function isBlacklisted(int $userId, string $spotifyArtistId): bool
    {
        return static::where('user_id', $userId)
            ->where('spotify_artist_id', $spotifyArtistId)
            ->exists();
    }

    /**
     * Get all blacklisted artist IDs for a user
     */
    public static function getBlacklistedArtistIds(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('spotify_artist_id')
            ->toArray();
    }
}