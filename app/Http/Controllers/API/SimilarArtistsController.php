<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SpotifyService;
use App\Services\RapidApiSpotifyService;
use App\Http\Integrations\Spotify\SpotifyClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimilarArtistsController extends Controller
{
    public function __construct(
        private readonly SpotifyClient $spotifyClient,
        private readonly ?RapidApiSpotifyService $rapidApiSpotifyService = null
    ) {
    }

    /**
     * Search for artists by name using RapidAPI Spotify
     */
    public function searchArtists(Request $request): JsonResponse
    {
        $query = $request->get('query', '');
        $limit = $request->get('limit', 20);
        
        if (empty(trim($query))) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('🔍 [SIMILAR ARTISTS] Searching artists with RapidAPI Spotify', [
                'query' => $query,
                'limit' => $limit,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled(),
                'has_service' => !!$this->rapidApiSpotifyService
            ]);

            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                Log::info('🔍 [SIMILAR ARTISTS] Delegating search to RapidApiSpotifyService (3-tier with rate limiting)', [
                    'query' => $query,
                    'limit' => $limit
                ]);

                $artists = $this->rapidApiSpotifyService->searchArtists($query, $limit);

                if (!empty($artists)) {
                    $deduplicatedArtists = $this->removeDuplicateArtists($artists);

                    Log::info('🔍 [SIMILAR ARTISTS] RapidAPI Spotify search successful (service handled provider selection)', [
                        'query' => $query,
                        'original_count' => count($artists),
                        'deduplicated_count' => count($deduplicatedArtists),
                        'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $deduplicatedArtists), 0, 3)
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $deduplicatedArtists
                    ]);
                }
            }

            Log::warning('🔍 [SIMILAR ARTISTS] RapidAPI Spotify search failed across all providers', [
                'query' => $query,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled(),
                'has_service' => !!$this->rapidApiSpotifyService
            ]);

            return response()->json([
                'success' => false,
                'message' => 'All RapidAPI search services failed. Please check API configurations.',
                'data' => []
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔍 [SIMILAR ARTISTS] Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search artists: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get similar artists by Spotify Artist ID or MBID
     */
    public function getSimilarArtists(Request $request): JsonResponse
    {
        $artistId = $request->get('artist_id', '');
        $mbid = $request->get('mbid', '');
        $limit = $request->get('limit', 20);

        if (empty(trim($artistId)) && empty(trim($mbid))) {
            return response()->json([
                'success' => false,
                'message' => 'Artist ID or MBID parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('🎵 [SIMILAR ARTISTS] Getting similar artists', [
                'artist_id' => $artistId,
                'mbid' => $mbid,
                'limit' => $limit,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled()
            ]);

            // Try RapidAPI Spotify if we have an artist ID
            if (!empty(trim($artistId)) && RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                $artists = $this->rapidApiSpotifyService->getSimilarArtists($artistId, $limit);

                if (!empty($artists)) {
                    $deduped = $this->removeDuplicateArtists($artists);

                    Log::info('🎵 [SIMILAR ARTISTS] Similar artists successful', [
                        'artist_id' => $artistId,
                        'total_count' => count($deduped),
                        'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $deduped), 0, 5)
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $deduped
                    ]);
                } else {
                    Log::warning('🎵 [SIMILAR ARTISTS] No similar artists found', [
                        'artist_id' => $artistId
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No similar artists found. This artist may not have enough data in music databases to generate recommendations.',
                'data' => []
            ]);

        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] Failed to get similar artists', [
                'artist_id' => $artistId,
                'mbid' => $mbid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar artists: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get followers count for multiple artists in batch using RapidAPI Spotify
     */
    public function batchGetArtistListeners(Request $request): JsonResponse
    {
        $artistIds = $request->get('artist_ids', []);

        if (!is_array($artistIds) || empty($artistIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Artist IDs array is required',
                'data' => []
            ]);
        }

        try {
            Log::info('📊 [SIMILAR ARTISTS] Batch processing artist followers', [
                'artist_ids_count' => count($artistIds),
                'rapidapi_enabled' => RapidApiSpotifyService::enabled()
            ]);

            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                $followersData = $this->rapidApiSpotifyService->getBatchArtistFollowers($artistIds);

                if (!empty($followersData)) {
                    Log::info('📊 [SIMILAR ARTISTS] RapidAPI batch followers successful (service handled provider selection)', [
                        'requested_count' => count($artistIds),
                        'returned_count' => count($followersData),
                        'success_rate' => round((count($followersData) / count($artistIds)) * 100, 1) . '%'
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $followersData
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'All RapidAPI batch followers services failed',
                'data' => []
            ]);

        } catch (\Exception $e) {
            Log::error('📊 [SIMILAR ARTISTS] Failed to batch get artist followers', [
                'artist_ids_count' => count($artistIds),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get artist followers: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get Spotify preview tracks for an artist using RapidAPI Spotify
     */
    public function getSpotifyPreview(Request $request): JsonResponse
    {
        $artistName = $request->get('artist_name', '');
        $artistId = $request->get('artist_id', '');
        $limit = $request->get('limit', 1);
        
        if (empty(trim($artistName)) && empty(trim($artistId))) {
            return response()->json([
                'success' => false,
                'message' => 'Artist name or artist ID parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('🎵 [SIMILAR ARTISTS] Getting Spotify preview', [
                'artist_name' => $artistName,
                'artist_id' => $artistId,
                'limit' => $limit,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled()
            ]);

            // Try RapidAPI Spotify first if we have an artist ID
            if (!empty($artistId) && RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                // Request 10 tracks to have better chance of finding non-remix tracks
                $tracks = $this->rapidApiSpotifyService->getArtistPreviewTracks($artistId, 10);

                // Filter out remixes and non-playable tracks
                $playable = array_values(array_filter($tracks, function ($t) {
                    $isPlayable = !empty($t['external_url']) || !empty($t['preview_url']);
                    $isNotRemix = !$this->isRemixTrack($t['name'] ?? '');
                    return $isPlayable && $isNotRemix;
                }));

                // Only return the first track (most popular non-remix)
                if (!empty($playable)) {
                    Log::info('🎵 [SIMILAR ARTISTS] RapidAPI Spotify preview successful', [
                        'artist_id' => $artistId,
                        'total_tracks' => count($tracks),
                        'non_remix_tracks' => count($playable),
                        'returning_track' => $playable[0]['name'] ?? 'unknown'
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'artist' => [
                                'id' => $artistId,
                                'name' => $artistName
                            ],
                            'tracks' => array_slice($playable, 0, $limit)
                        ]
                    ]);
                }
            }

            // Fallback to regular Spotify client if we have artist name OR only artistId
            if ((!empty($artistName) || !empty($artistId)) && SpotifyService::enabled()) {
                Log::info('🎵 [SIMILAR ARTISTS] Falling back to regular Spotify client');

                $spotifyArtist = null;

                // If we already have artistId, use it; otherwise search by name
                if (empty($artistId)) {
                    $artistResults = $this->spotifyClient->search($artistName, 'artist', ['limit' => 10]);
                    if (empty($artistResults['artists']['items'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Artist not found on Spotify',
                            'data' => []
                        ]);
                    }
                    // Find exact match or fallback to first
                    $artistNameLower = strtolower($artistName);
                    foreach ($artistResults['artists']['items'] as $artist) {
                        if (strtolower($artist['name']) === $artistNameLower) {
                            $spotifyArtist = $artist;
                            break;
                        }
                    }
                    if (!$spotifyArtist) {
                        $spotifyArtist = $artistResults['artists']['items'][0];
                    }
                    $artistId = $spotifyArtist['id'];
                } else {
                    // We have artistId, fetch artist details from Spotify
                    $artistDetails = $this->spotifyClient->getArtist($artistId);
                    if (!empty($artistDetails)) {
                        $spotifyArtist = $artistDetails;
                    }
                }

                // If we still don't have artist details, return error
                if (!$spotifyArtist) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not fetch artist details from Spotify',
                        'data' => []
                    ]);
                }

                // Get top tracks for the artist
                $topTracksResult = $this->spotifyClient->getArtistTopTracks($artistId, ['market' => 'US']);

                // Get up to 10 non-remix tracks with preview URLs and oembed data
                $tracks = [];
                foreach ($topTracksResult['tracks'] as $track) {
                    if (count($tracks) >= 10) break;

                    // Skip remix tracks
                    if ($this->isRemixTrack($track['name'])) {
                        continue;
                    }

                    // Get Spotify oembed data for this track
                    $oembedData = $this->getSpotifyOEmbedData($track['id']);

                    $tracks[] = [
                        'id' => $track['id'],
                        'name' => $track['name'],
                        'artists' => array_map(fn($artist) => ['name' => $artist['name']], $track['artists']),
                        'preview_url' => $track['preview_url'] ?? null,
                        'external_url' => $track['external_urls']['spotify'] ?? null,
                        'duration_ms' => $track['duration_ms'] ?? null,
                        'oembed' => $oembedData,
                    ];
                }
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'artist' => [
                            'id' => $spotifyArtist['id'],
                            'name' => $spotifyArtist['name'],
                            'external_url' => $spotifyArtist['external_urls']['spotify'] ?? null,
                        ],
                        'tracks' => $tracks
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No preview service available or invalid parameters',
                'data' => []
            ]);
            
        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] Failed to get Spotify preview', [
                'artist_name' => $artistName,
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Spotify preview: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get track popularity using RapidAPI Spotify
     */
    public function getTrackPopularity(Request $request): JsonResponse
    {
        $trackId = $request->get('track_id', '');
        
        if (empty(trim($trackId))) {
            return response()->json([
                'success' => false,
                'message' => 'Track ID parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('📈 [SIMILAR ARTISTS] Getting track popularity', [
                'track_id' => $trackId,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled()
            ]);

            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                $trackData = $this->rapidApiSpotifyService->getTrackPopularity($trackId);
                
                if (!empty($trackData)) {
                    Log::info('📈 [SIMILAR ARTISTS] Track popularity successful', [
                        'track_id' => $trackId,
                        'popularity' => $trackData['popularity'] ?? 0
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $trackData
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Track popularity service not available',
                'data' => []
            ]);
            
        } catch (\Exception $e) {
            Log::error('📈 [SIMILAR ARTISTS] Failed to get track popularity', [
                'track_id' => $trackId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get track popularity: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove duplicate artists from search results
     */
    private function removeDuplicateArtists(array $artists): array
    {
        $seen = [];
        $uniqueArtists = [];
        
        foreach ($artists as $artist) {
            // Create a unique key based on normalized name and Spotify ID
            $name = strtolower(trim($artist['name'] ?? ''));
            $spotifyId = $artist['id'] ?? '';
            
            // Use Spotify ID as primary key if available, otherwise use normalized name
            $key = !empty($spotifyId) ? $spotifyId : $name;
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueArtists[] = $artist;
            }
        }
        
        Log::info('🔍 [SIMILAR ARTISTS] Deduplication completed', [
            'original_count' => count($artists),
            'unique_count' => count($uniqueArtists),
            'duplicates_removed' => count($artists) - count($uniqueArtists)
        ]);
        
        return $uniqueArtists;
    }

    /**
     * Get Spotify oEmbed data for a track
     */
    private function getSpotifyOEmbedData(string $spotifyTrackId): ?array
    {
        try {
            $spotifyUrl = "https://open.spotify.com/track/$spotifyTrackId";

            $response = \Illuminate\Support\Facades\Http::get("https://open.spotify.com/oembed", [
                'url' => $spotifyUrl,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $oEmbedData = $response->json();
                Log::info("🎵 Similar Artists: Got Spotify oEmbed data", [
                    'spotify_track_id' => $spotifyTrackId
                ]);
                return $oEmbedData;
            }

            Log::warning("🎵 Similar Artists: Failed to get oEmbed", [
                'spotify_track_id' => $spotifyTrackId,
                'status' => $response->status()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Similar Artists: Spotify oEmbed error', [
                'track_id' => $spotifyTrackId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if track title contains remix indicators
     */
    private function isRemixTrack(string $trackName): bool
    {
        $remixIndicators = [
            'remix',
            'remixed',
            'rework',
            'vip mix',
            'extended mix',
            'radio edit',
            'club mix',
            'dub mix',
            'instrumental mix',
            'acoustic version',
            'live version',
            'remaster'
        ];

        $trackNameLower = strtolower($trackName);

        foreach ($remixIndicators as $indicator) {
            if (str_contains($trackNameLower, $indicator)) {
                Log::info('🎵 [SIMILAR ARTISTS] Filtered out remix/version track', [
                    'track_name' => $trackName,
                    'indicator' => $indicator
                ]);
                return true;
            }
        }

        return false;
    }
}
