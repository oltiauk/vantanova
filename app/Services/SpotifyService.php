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

    public function getArtist(string $artistId): ?array
    {
        if (!static::enabled()) {
            return null;
        }

        try {
            return $this->client->getArtist($artistId);
        } catch (\Exception $e) {
            Log::error('Spotify artist details error', [
                'message' => $e->getMessage(),
                'artist_id' => $artistId
            ]);
            return null;
        }
    }

    public function getAlbum(string $albumId): ?array
    {
        if (!static::enabled()) {
            return null;
        }

        try {
            return $this->client->getAlbum($albumId);
        } catch (\Exception $e) {
            Log::error('Spotify album details error', [
                'message' => $e->getMessage(),
                'album_id' => $albumId
            ]);
            return null;
        }
    }

    // === NEW MUSIC DISCOVERY METHODS ===

    /**
     * Search for tracks on Spotify for music discovery
     */
    public function searchTracks(string $query, int $limit = 20): array
    {
        // EMERGENCY DISABLE: This method was causing infinite API loops and expensive costs
        Log::warning('🚨 [SPOTIFY SERVICE] Search tracks method DISABLED to prevent API costs', [
            'query' => $query,
            'limit' => $limit,
            'disabled_at' => now()->toISOString()
        ]);

        return ['tracks' => ['items' => []]];
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

    /**
     * Search for albums on Spotify
     */
    public function searchAlbums(string $query, int $limit = 20): array
    {
        if (!static::enabled()) {
            return ['albums' => ['items' => []]];
        }

        try {
            return $this->client->search($query, 'album', ['limit' => $limit]);

        } catch (\Exception $e) {
            Log::error('Spotify album search error', [
                'message' => $e->getMessage(),
                'query' => $query
            ]);
            return ['albums' => ['items' => []]];
        }
    }

    /**
     * Get multiple albums with their tracks in batch
     */
    public function batchGetAlbumsWithTracks(array $albumIds): array
    {
        if (!static::enabled() || empty($albumIds)) {
            return [];
        }

        try {
            // Split into chunks of 20 (Spotify's limit for albums)
            $chunks = array_chunk($albumIds, 20);
            $allAlbums = [];

            foreach ($chunks as $chunk) {
                // Get albums with tracks included
                $albums = $this->client->getAlbums($chunk, ['market' => 'US']);
                Log::info('Batch albums chunk result', [
                    'chunk_size' => count($chunk),
                    'albums_returned' => isset($albums['albums']) ? count($albums['albums']) : 0,
                    'first_album_has_tracks' => isset($albums['albums'][0]['tracks']) ? 'yes' : 'no'
                ]);

                if ($albums && isset($albums['albums'])) {
                    $allAlbums = array_merge($allAlbums, array_filter($albums['albums'])); // Filter out null albums
                }
            }

            return $allAlbums;

        } catch (\Exception $e) {
            Log::error('Spotify batch albums error', [
                'message' => $e->getMessage(),
                'album_ids' => $albumIds
            ]);
            return [];
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