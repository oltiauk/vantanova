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
        'spotify_artist_id',
        'label',
        'popularity',
        'followers',
        'streams',
        'release_date',
        'preview_url',
        'track_count',
        'is_single_track',
        'album_id',
        'expires_at',
        'is_hidden',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_single_track' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a track is saved for a user (and not hidden)
     */
    public static function isSaved(int $userId, string $isrc): bool
    {
        return static::where('user_id', $userId)
            ->where('isrc', $isrc)
            ->where('is_hidden', false)
            ->exists();
    }

    /**
     * Get all saved ISRCs for a user (not hidden)
     */
    public static function getSavedIsrcs(int $userId): array
    {
        return static::where('user_id', $userId)
            ->where('is_hidden', false)
            ->pluck('isrc')
            ->toArray();
    }

    /**
     * Create or update a saved track (permanent storage)
     * Ensures user_id, spotify_id, and spotify_artist_id are always stored
     */
    public static function saveTrack(
        string|int $userId,
        ?string $isrc,
        string $trackName,
        string $artistName,
        ?string $spotifyId = null,
        ?string $spotifyArtistId = null,
        ?string $label = null,
        ?int $popularity = null,
        ?int $followers = null,
        ?int $streams = null,
        ?string $releaseDate = null,
        ?string $previewUrl = null,
        ?int $trackCount = null,
        ?bool $isSingleTrack = true,
        ?string $albumId = null
    ): static {
        // Use ISRC as primary identifier if available, otherwise use spotify_id
        $uniqueKey = $isrc
            ? ['user_id' => $userId, 'isrc' => $isrc]
            : ['user_id' => $userId, 'spotify_id' => $spotifyId];

        return static::updateOrCreate(
            $uniqueKey,
            [
                'isrc' => $isrc,
                'track_name' => $trackName,
                'artist_name' => $artistName,
                'spotify_id' => $spotifyId,
                'spotify_artist_id' => $spotifyArtistId,
                'label' => $label,
                'popularity' => $popularity,
                'followers' => $followers,
                'streams' => $streams,
                'release_date' => $releaseDate,
                'preview_url' => $previewUrl,
                'track_count' => $trackCount,
                'is_single_track' => $isSingleTrack,
                'album_id' => $albumId,
                'is_hidden' => false, // When saving, ensure it's visible
                'expires_at' => null, // Permanent storage - no expiration
            ]
        );
    }

    /**
     * Hide a saved track (permanent storage - track stays in DB)
     */
    public static function hideTrack(int $userId, int $trackId): bool
    {
        return static::where('user_id', $userId)
            ->where('id', $trackId)
            ->update(['is_hidden' => true]) > 0;
    }
}