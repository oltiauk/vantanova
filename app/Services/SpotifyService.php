<?php

namespace App\Services;

use App\Http\Integrations\Spotify\SpotifyClient;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SpotifyService
{
    public function __construct(private readonly SpotifyClient $client)
    {
    }

    public static function enabled(): bool
    {
        return config('koel.services.spotify.client_id') && config('koel.services.spotify.client_secret');
    }

    public function tryGetArtistImage(Artist $artist): ?string
    {
        if (!static::enabled()) {
            return null;
        }

        if ($artist->is_various || $artist->is_unknown) {
            return null;
        }

        return Arr::get(
            $this->client->search($artist->name, 'artist', ['limit' => 1]),
            'artists.items.0.images.0.url'
        );
    }

    public function tryGetAlbumCover(Album $album): ?string
    {
        if (!static::enabled()) {
            return null;
        }

        if ($album->is_unknown || $album->artist->is_unknown || $album->artist->is_various) {
            return null;
        }

        return Arr::get(
            $this->client->search("$album->name artist:{$album->artist->name}", 'album', ['limit' => 1]),
            'albums.items.0.images.0.url'
        );
    }

    // === NEW MUSIC DISCOVERY METHODS ===

    /**
     * Search for tracks on Spotify for music discovery
     */
    public function searchTracks(string $query, int $limit = 20): array
    {
        if (!static::enabled()) {
            Log::warning('🔍 [SPOTIFY SERVICE] Spotify not enabled');
            return ['tracks' => ['items' => []]];
        }

        try {
            // Spotify API limits: min=1, max=50, default=20
            $spotifyLimit = max(1, min(50, $limit));
            
            Log::info('🔍 [SPOTIFY SERVICE] Making Spotify API call', [
                'query' => $query,
                'original_limit' => $limit,
                'spotify_limit' => $spotifyLimit,
                'search_type' => 'track'
            ]);
            
            $response = $this->client->search($query, 'track', ['limit' => $spotifyLimit]);
            
            Log::info('🔍 [SPOTIFY SERVICE] Spotify API response received', [
                'response_type' => gettype($response),
                'has_tracks_key' => isset($response['tracks']),
                'tracks_count' => count($response['tracks']['items'] ?? []),
                'sample_track_names' => array_slice(
                    array_map(fn($track) => $track['name'] ?? 'unknown', $response['tracks']['items'] ?? []), 
                    0, 3
                )
            ]);
            
            return $response ?: ['tracks' => ['items' => []]];

        } catch (\Exception $e) {
            Log::error('🔍 [SPOTIFY SERVICE] Spotify track search error', [
                'message' => $e->getMessage(),
                'query' => $query,
                'limit' => $limit,
                'spotify_limit' => $spotifyLimit ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return ['tracks' => ['items' => []]];
        }
    }

    /**
     * Get track details by ID
     */
    public function getTrackDetails(string $trackId): ?array
    {
        if (!static::enabled()) {
            return null;
        }

        try {
            return $this->client->getTrack($trackId);

        } catch (\Exception $e) {
            Log::error('Spotify track details error', [
                'message' => $e->getMessage(),
                'track_id' => $trackId
            ]);
            return null;
        }
    }

    /**
     * Get multiple tracks by IDs (batch request)
     */
    public function batchGetTracks(array $trackIds): array
    {
        if (!static::enabled() || empty($trackIds)) {
            return ['tracks' => []];
        }

        try {
            // Split into chunks of 50 (Spotify's limit)
            $chunks = array_chunk($trackIds, 50);
            $allTracks = [];

            foreach ($chunks as $chunk) {
                $tracks = $this->client->getTracks($chunk);
                if ($tracks && isset($tracks['tracks'])) {
                    $allTracks = array_merge($allTracks, $tracks['tracks']);
                }
            }

            return ['tracks' => $allTracks];

        } catch (\Exception $e) {
            Log::error('Spotify batch tracks error', [
                'message' => $e->getMessage(),
                'track_ids' => $trackIds
            ]);
            return ['tracks' => []];
        }
    }

    public function getBatchAudioFeatures(array $trackIds): array
{
    // Spotify supports up to 100 tracks per request
    $chunks = array_chunk($trackIds, 100);
    $allFeatures = [];
    
    foreach ($chunks as $chunk) {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ])->get('https://api.spotify.com/v1/audio-features', [
            'ids' => implode(',', $chunk)
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            $allFeatures = array_merge($allFeatures, $data['audio_features'] ?? []);
        }
    }
    
    return $allFeatures;
}

    /**
     * Get track audio features (for BPM, key, etc.)
     */
    public function getTrackAudioFeatures(string $trackId): ?array
    {
        if (!static::enabled()) {
            return null;
        }

        try {
            return $this->client->getAudioFeatures($trackId);

        } catch (\Exception $e) {
            Log::error('Spotify audio features error', [
                'message' => $e->getMessage(),
                'track_id' => $trackId
            ]);
            return null;
        }
    }

    /**
     * Get multiple track audio features
     */
    public function batchGetAudioFeatures(array $trackIds): array
    {
        if (!static::enabled() || empty($trackIds)) {
            return ['audio_features' => []];
        }

        try {
            // Split into chunks of 100 (Spotify's limit for audio features)
            $chunks = array_chunk($trackIds, 100);
            $allFeatures = [];

            foreach ($chunks as $chunk) {
                $features = $this->client->getAudioFeatures($chunk);
                if ($features && isset($features['audio_features'])) {
                    $allFeatures = array_merge($allFeatures, $features['audio_features']);
                }
            }

            return ['audio_features' => $allFeatures];

        } catch (\Exception $e) {
            Log::error('Spotify batch audio features error', [
                'message' => $e->getMessage(),
                'track_ids' => $trackIds
            ]);
            return ['audio_features' => []];
        }
    }
}