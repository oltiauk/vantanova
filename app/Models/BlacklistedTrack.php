<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlacklistedTrack extends Model
{
    protected $fillable = [
        'user_id',
        'isrc',
        'track_name',
        'artist_name',
        'spotify_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a track is blacklisted for a user
     */
    public static function isBlacklisted(string|int $userId, string $isrc): bool
    {
        return static::where('user_id', $userId)
            ->where('isrc', $isrc)
            ->exists();
    }

    /**
     * Get all blacklisted ISRCs for a user
     */
    public static function getBlacklistedIsrcs(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('isrc')
            ->toArray();
    }
}