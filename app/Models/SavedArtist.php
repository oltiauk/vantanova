<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedArtist extends Model
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
     * Check if an artist is saved for a user
     */
    public static function isSaved(int $userId, string $spotifyArtistId): bool
    {
        return static::where('user_id', $userId)
            ->where('spotify_artist_id', $spotifyArtistId)
            ->exists();
    }

    /**
     * Get all saved artist IDs for a user
     */
    public static function getSavedArtistIds(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('spotify_artist_id')
            ->toArray();
    }
}