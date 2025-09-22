<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpotifyCache extends Model
{
    protected $table = 'spotify_cache';

    protected $fillable = [
        'spotify_id',
        'type',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Get cached data for a Spotify entity
     */
    public static function getCached(string $spotifyId, string $type): ?array
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('spotify_cache')) {
                return null;
            }

            $cached = static::where('spotify_id', $spotifyId)
                ->where('type', $type)
                ->where('expires_at', '>', now())
                ->first();

            return $cached?->data;
        } catch (\Exception $e) {
            // Table doesn't exist or other error, return null
            return null;
        }
    }

    /**
     * Cache data for a Spotify entity
     */
    public static function setCached(string $spotifyId, string $type, array $data, int $hoursToExpire = 24): void
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('spotify_cache')) {
                return;
            }

            static::updateOrCreate(
                ['spotify_id' => $spotifyId, 'type' => $type],
                [
                    'data' => $data,
                    'expires_at' => now()->addHours($hoursToExpire),
                ]
            );
        } catch (\Exception $e) {
            // Table doesn't exist or other error, silently fail
            \Log::debug('Spotify cache operation failed', [
                'error' => $e->getMessage(),
                'spotify_id' => $spotifyId,
                'type' => $type
            ]);
        }
    }

    /**
     * Remove expired cache entries
     */
    public static function removeExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Clear all cached data for a specific type
     */
    public static function clearType(string $type): int
    {
        return static::where('type', $type)->delete();
    }
}