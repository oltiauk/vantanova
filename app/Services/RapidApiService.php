<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RapidApiService
{
    private string $baseUrl;
    private string $host;
    private string $key;
    private float $rateLimitDelay = 0.2; // 5 req/sec limit

    public function __construct()
    {
        $this->baseUrl = config('services.rapidapi.base_url');
        $this->host = config('services.rapidapi.host');
        $this->key = config('services.rapidapi.key');
    }

    public static function enabled(): bool
    {
        return config('services.rapidapi.key') && config('services.rapidapi.host');
    }

    /**
     * Make a rate-limited request to RapidAPI
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        if (!static::enabled()) {
            return ['success' => false, 'error' => 'RapidAPI not configured'];
        }

        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'X-RapidAPI-Key' => $this->key,
            'X-RapidAPI-Host' => $this->host,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache'
        ];

        try {
            Log::info('RapidAPI Request', ['endpoint' => $endpoint, 'params' => $params]);
            
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->withoutVerifying() // Disable SSL verification if needed
                ->get($url, $params);

            // Rate limiting
            usleep($this->rateLimitDelay * 1000000);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('RapidAPI Response Success', ['status' => $response->status()]);
                return ['success' => true, 'data' => $data];
            } else {
                Log::error('RapidAPI Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return ['success' => false, 'error' => $response->body(), 'status' => $response->status()];
            }

        } catch (\Exception $e) {
            Log::error('RapidAPI Request Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Step 1: Search for tracks (autocomplete support)
     */
    public function searchTracks(string $query, int $limit = 5): array
    {
        $result = $this->makeRequest('/search/', [
            'q' => $query,
            'type' => 'tracks',
            'limit' => $limit
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        $data = $result['data'];
        $tracks = [];

        try {
            // Extract tracks from search results
            if (isset($data['tracks']['items'])) {
                foreach ($data['tracks']['items'] as $item) {
                    $trackData = $item['data'] ?? $item;
                    
                    // Get artist name
                    $artists = $trackData['artists']['items'] ?? [];
                    $artistName = 'Unknown Artist';
                    if (!empty($artists)) {
                        $artistName = $artists[0]['profile']['name'] ?? 'Unknown Artist';
                    }

                    // Get album info
                    $albumData = $trackData['albumOfTrack'] ?? [];
                    $albumName = $albumData['name'] ?? 'Unknown Album';
                    $albumImage = null;
                    if (isset($albumData['coverArt']['sources'][0]['url'])) {
                        $albumImage = $albumData['coverArt']['sources'][0]['url'];
                    }

                    $tracks[] = [
                        'id' => $trackData['id'] ?? '',
                        'uri' => $trackData['uri'] ?? '',
                        'name' => $trackData['name'] ?? 'Unknown Track',
                        'artist' => $artistName,
                        'album' => $albumName,
                        'image' => $albumImage,
                        'duration_ms' => $trackData['duration']['totalMilliseconds'] ?? 0,
                        'preview_url' => $trackData['previewUrl'] ?? null,
                        'popularity' => $trackData['playcount'] ?? 0,
                        'external_url' => "https://open.spotify.com/track/" . ($trackData['id'] ?? '')
                    ];
                }
            }

            return ['success' => true, 'tracks' => $tracks];

        } catch (\Exception $e) {
            Log::error('RapidAPI Search Parsing Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to parse search results'];
        }
    }

    /**
     * Step 2: Create radio playlist from track URI
     */
    public function createRadioPlaylist(string $trackUri): array
    {
        $result = $this->makeRequest('/seed_to_playlist/', [
            'uri' => $trackUri,
            '_t' => time(),
            '_r' => rand(1000, 9999) // Add random number for uniqueness
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        $data = $result['data'];

        try {
            $mediaItems = $data['mediaItems'] ?? [];
            
            if (!empty($mediaItems)) {
                $playlistUri = $mediaItems[0]['uri'] ?? '';
                
                if ($playlistUri) {
                    // Extract playlist ID from URI: spotify:playlist:37i9dQZF1E8OKymaBvi7km
                    $playlistId = '';
                    if (strpos($playlistUri, ':') !== false) {
                        $parts = explode(':', $playlistUri);
                        $playlistId = end($parts);
                    }

                    return [
                        'success' => true,
                        'playlist_uri' => $playlistUri,
                        'playlist_id' => $playlistId
                    ];
                }
            }

            return ['success' => false, 'error' => 'No playlist created'];

        } catch (\Exception $e) {
            Log::error('RapidAPI Radio Playlist Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create radio playlist'];
        }
    }

    /**
     * Step 3: Get tracks from radio playlist
     */
    public function getPlaylistTracks(string $playlistId, int $limit = 100): array
    {
        $result = $this->makeRequest('/playlist_tracks/', [
            'id' => $playlistId,
            'offset' => '0',
            'limit' => (string) $limit
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        $data = $result['data'];
        $tracks = [];

        try {
            $items = $data['items'] ?? [];
            
            foreach ($items as $item) {
                $track = $item['track'] ?? [];
                
                if (empty($track)) continue;

                // Get artist info
                $artists = $track['artists'] ?? [];
                $artistName = 'Unknown Artist';
                $artistId = '';
                if (!empty($artists)) {
                    $artistName = $artists[0]['name'] ?? 'Unknown Artist';
                    $artistId = $artists[0]['id'] ?? '';
                }

                // Get album info
                $album = $track['album'] ?? [];
                $albumName = $album['name'] ?? 'Unknown Album';
                $albumId = $album['id'] ?? '';
                $releaseDate = $album['release_date'] ?? '';
                $albumImage = null;
                if (!empty($album['images'])) {
                    $albumImage = $album['images'][0]['url'] ?? null;
                }

                $tracks[] = [
                    'id' => $track['id'] ?? '',
                    'uri' => $track['uri'] ?? '',
                    'name' => $track['name'] ?? 'Unknown Track',
                    'artist' => $artistName,
                    'artist_id' => $artistId,
                    'album' => $albumName,
                    'album_id' => $albumId,
                    'release_date' => $releaseDate,
                    'image' => $albumImage,
                    'duration_ms' => $track['duration_ms'] ?? 0,
                    'popularity' => $track['popularity'] ?? 0,
                    'explicit' => $track['explicit'] ?? false,
                    'preview_url' => $track['preview_url'] ?? null,
                    'external_url' => $track['external_urls']['spotify'] ?? '',
                    'artists' => $artists
                ];
            }

            return [
                'success' => true,
                'tracks' => $tracks,
                'total' => $data['total'] ?? count($tracks)
            ];

        } catch (\Exception $e) {
            Log::error('RapidAPI Playlist Tracks Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to get playlist tracks'];
        }
    }

    /**
     * Step 4: Filter tracks by popularity
     */
    public function filterTracksByPopularity(array $tracks, int $maxPopularity = 70): array
    {
        return array_filter($tracks, function($track) use ($maxPopularity) {
            $popularity = $track['popularity'] ?? 0;
            return is_numeric($popularity) && $popularity <= $maxPopularity;
        });
    }

    /**
     * Complete radio workflow: search → create playlist → get tracks → filter
     */
    public function getRadioRecommendations(string $trackUri, int $maxPopularity = 70, int $limit = 50): array
    {
        Log::info('RapidAPI Radio Workflow Started', ['track_uri' => $trackUri]);

        // Step 2: Create radio playlist
        $radioResult = $this->createRadioPlaylist($trackUri);
        if (!$radioResult['success']) {
            return $radioResult;
        }

        // Step 3: Get playlist tracks
        $tracksResult = $this->getPlaylistTracks($radioResult['playlist_id'], 100);
        if (!$tracksResult['success']) {
            return $tracksResult;
        }

        // Step 4: Filter by popularity
        $filteredTracks = $this->filterTracksByPopularity($tracksResult['tracks'], $maxPopularity);
        
        // Limit results
        $limitedTracks = array_slice($filteredTracks, 0, $limit);

        Log::info('RapidAPI Radio Workflow Completed', [
            'total_tracks' => count($tracksResult['tracks']),
            'filtered_tracks' => count($filteredTracks),
            'final_tracks' => count($limitedTracks)
        ]);

        return [
            'success' => true,
            'tracks' => $limitedTracks,
            'total_found' => count($tracksResult['tracks']),
            'after_filtering' => count($filteredTracks),
            'playlist_id' => $radioResult['playlist_id']
        ];
    }

    /**
     * Get batch album data for labels (Step 4 enhancement)
     */
    public function getBatchAlbumData(array $albumIds): array
    {
        if (empty($albumIds)) {
            return ['success' => true, 'albums' => []];
        }

        // Limit to 10 albums as per documentation
        $limitedIds = array_slice($albumIds, 0, 10);
        $idsString = implode(',', $limitedIds);

        $result = $this->makeRequest('/albums/', [
            'ids' => $idsString
        ]);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];
        $albums = [];

        try {
            if (isset($data['albums'])) {
                foreach ($data['albums'] as $album) {
                    $albums[] = [
                        'id' => $album['id'] ?? '',
                        'name' => $album['name'] ?? '',
                        'label' => $album['label'] ?? 'Unknown Label'
                    ];
                }
            }

            return ['success' => true, 'albums' => $albums];

        } catch (\Exception $e) {
            Log::error('RapidAPI Batch Album Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to get album data'];
        }
    }

    /**
     * Get batch artist data for follower counts (Step 4 enhancement)
     */
    public function getBatchArtistData(array $artistIds): array
    {
        if (empty($artistIds)) {
            return ['success' => true, 'artists' => []];
        }

        // Limit to 50 artists as per documentation
        $limitedIds = array_slice($artistIds, 0, 50);
        $idsString = implode(',', $limitedIds);

        $result = $this->makeRequest('/artists/', [
            'ids' => $idsString
        ]);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];
        $artists = [];

        try {
            if (isset($data['artists'])) {
                foreach ($data['artists'] as $artist) {
                    $artists[] = [
                        'id' => $artist['id'] ?? '',
                        'name' => $artist['name'] ?? '',
                        'followers' => $artist['followers']['total'] ?? 0
                    ];
                }
            }

            return ['success' => true, 'artists' => $artists];

        } catch (\Exception $e) {
            Log::error('RapidAPI Batch Artist Error', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to get artist data'];
        }
    }
}