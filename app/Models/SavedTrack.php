<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class SavedTrack extends Model
{
    protected $fillable = [
        'user_id',
        'isrc',
        'track_name',
        'artist_name',
        'spotify_id',
        'label',
        'popularity',
        'followers',
        'release_date',
        'preview_url',
        'track_count',
        'is_single_track',
        'album_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_single_track' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a track is saved for a user (and not expired)
     */
    public static function isSaved(int $userId, string $isrc): bool
    {
        return static::where('user_id', $userId)
            ->where('isrc', $isrc)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get all saved ISRCs for a user (non-expired)
     */
    public static function getSavedIsrcs(int $userId): array
    {
        return static::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->pluck('isrc')
            ->toArray();
    }

    /**
     * Create a saved track with 24-hour expiration
     */
    public static function saveTrack(
        string|int $userId,
        string $isrc,
        string $trackName,
        string $artistName,
        ?string $spotifyId = null,
        ?string $label = null,
        ?int $popularity = null,
        ?int $followers = null,
        ?string $releaseDate = null,
        ?string $previewUrl = null,
        ?int $trackCount = null,
        ?bool $isSingleTrack = true,
        ?string $albumId = null
    ): static {
        return static::updateOrCreate(
            ['user_id' => $userId, 'isrc' => $isrc],
            [
                'track_name' => $trackName,
                'artist_name' => $artistName,
                'spotify_id' => $spotifyId,
                'label' => $label,
                'popularity' => $popularity,
                'followers' => $followers,
                'release_date' => $releaseDate,
                'preview_url' => $previewUrl,
                'track_count' => $trackCount,
                'is_single_track' => $isSingleTrack,
                'album_id' => $albumId,
                'expires_at' => now()->addHours(24),
            ]
        );
    }

    /**
     * Remove expired saved tracks
     */
    public static function removeExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}