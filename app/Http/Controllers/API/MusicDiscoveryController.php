<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SpotifyService;
use App\Services\SoundStatsService;
use App\Services\RapidApiService;
use App\Services\RapidApiSpotifyService;
use App\Services\LastfmService;
use App\Models\BlacklistedTrack;
use App\Models\SavedTrack;
use App\Models\BlacklistedArtist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MusicDiscoveryController extends Controller
{
    public function __construct(
        private ?SpotifyService $spotifyService = null,
        private ?SoundStatsService $soundStatsService = null,
        private ?RapidApiService $rapidApiService = null,
        private ?RapidApiSpotifyService $rapidApiSpotifyService = null
    ) {}

    /**
     * Log API request and response for tracking
     */
    private function logApiCall(string $service, string $endpoint, array $params = [], $response = null): void
    {
        $requestId = uniqid('api_');

        \Log::info("🔥 API_REQUEST_{$service}", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'params' => $params,
            'timestamp' => now()->toISOString()
        ]);

        if ($response) {
            \Log::info("🔥 API_RESPONSE_{$service}", [
                'request_id' => $requestId,
                'status' => $response->status(),
                'success' => $response->successful(),
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Wrapper for HTTP calls with automatic logging
     */
    private function makeApiCall(string $service, string $endpoint, string $method = 'GET', array $params = [], array $headers = []): \Illuminate\Http\Client\Response
    {
        $this->logApiCall($service, $endpoint, $params);

        $http = Http::timeout(30);

        if (!empty($headers)) {
            $http = $http->withHeaders($headers);
        }

        $response = match(strtoupper($method)) {
            'GET' => $http->get($endpoint, $params),
            'POST' => $http->post($endpoint, $params),
            'PUT' => $http->put($endpoint, $params),
            'DELETE' => $http->delete($endpoint, $params),
            default => $http->get($endpoint, $params)
        };

        $this->logApiCall($service, $endpoint, $params, $response);

        return $response;
    }
    
    private function getLastfmService(): ?LastfmService
    {
        try {
            $service = app(LastfmService::class);
            \Log::info("LASTFM_SERVICE_RESOLVED", [
                'service_class' => get_class($service),
                'enabled' => $service::enabled(),
                'api_key_set' => !empty(config('koel.services.lastfm.key')),
                'api_secret_set' => !empty(config('koel.services.lastfm.secret'))
            ]);
            return $service;
        } catch (\Exception $e) {
            \Log::info("LASTFM_SERVICE_RESOLVE_FAILED", ['error' => $e->getMessage()]);
            return null;
        }
    }


    /**
     * Get related tracks using Spotify, Shazam, and Last.fm APIs
     * GET /api/music-discovery/related-tracks
     */
    public function getRelatedTracks(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'track_id' => 'sometimes|string',
            'artist_name' => 'required|string',
            'track_title' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $artistName = $request->input('artist_name');
        $trackTitle = $request->input('track_title');
        $limit = $request->input('limit', 50);

        try {
            $allTracks = [];
            $spotifyTracks = [];

            // 1. Search for the track on RapidAPI Spotify to get Track ID
            $rapidApiSearchResults = null;
            if ($this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                $query = "{$artistName} {$trackTitle}";
                $rapidApiSearchResults = $this->rapidApiSpotifyService->searchTracks($query, 20, 'tracks');
            }

            \Log::info("🔍 RapidAPI Spotify search results", [
                'artist' => $artistName,
                'title' => $trackTitle,
                'found_results' => !empty($rapidApiSearchResults),
                'has_data' => isset($rapidApiSearchResults['data']),
                'track_count' => isset($rapidApiSearchResults['data']['tracks']) ? count($rapidApiSearchResults['data']['tracks']) : 0
            ]);
            $spotifyTrackId = null;

            if ($rapidApiSearchResults && isset($rapidApiSearchResults['data']['tracks'])) {
                $tracks = $rapidApiSearchResults['data']['tracks'];

                \Log::info("🔍 Using RapidAPI search results for track matching", [
                    'total_results' => count($tracks),
                    'first_result' => [
                        'id' => $tracks[0]['data']['id'] ?? 'unknown',
                        'artist' => $tracks[0]['data']['artists']['items'][0]['profile']['name'] ?? 'unknown',
                        'title' => $tracks[0]['data']['name'] ?? 'unknown'
                    ]
                ]);

                // Find exact match using RapidAPI service
                $spotifyTrackId = $this->rapidApiSpotifyService->findExactMatch(
                    $rapidApiSearchResults,
                    $artistName,
                    $trackTitle
                );

                if ($spotifyTrackId) {
                    \Log::info("🔍 Selected Spotify track via RapidAPI", [
                        'selected_id' => $spotifyTrackId,
                        'expected_artist' => $artistName,
                        'expected_title' => $trackTitle
                    ]);

                    \Log::info("DEBUG: SELECTED TRACK ID FOR {$artistName} - {$trackTitle}: {$spotifyTrackId}");

                    // Get Spotify recommendations using RapidAPI seed_to_playlist
                    $spotifyTracks = $this->getSpotifyRelatedTracks($spotifyTrackId, $limit, null);
                    \Log::info("🎧 Got Spotify tracks via RapidAPI", ['count' => count($spotifyTracks)]);
                } else {
                    \Log::warning("🔍 No suitable Spotify track match found via RapidAPI", [
                        'artist' => $artistName,
                        'title' => $trackTitle,
                        'search_results_count' => count($tracks)
                    ]);
                }
            }

            // Only use Spotify recommendations (Shazam and Last.fm removed)
            $allTracks = $spotifyTracks;

            // Log API usage summary
            \Log::info("🔥 API_USAGE_SUMMARY", [
                'total_spotify_calls' => count($spotifyTracks) > 0 ? 3 : 1, // Search + Seed-to-playlist + Playlist-tracks (if successful)
                'spotify_tracks_count' => count($spotifyTracks),
                'combined_tracks_count' => count($allTracks),
                'timestamp' => now()->toISOString()
            ]);

            // 6. Remove duplicates only
            $allTracks = $this->removeDuplicateTracks($allTracks);

            // 7. Format tracks to match frontend expectations BEFORE filtering
            $formattedTracks = $this->formatRelatedTracksArray($allTracks);

            // 8. Apply user preference filtering (blacklist filtering) on formatted tracks
            $userId = auth()->id();
            if ($userId) {
                $beforeCount = count($formattedTracks);
                $formattedTracks = $this->filterByUserPreferences($formattedTracks, $userId, $artistName);
                $afterCount = count($formattedTracks);
                \Log::info("🎧 Applied user preference filtering", ['before' => $beforeCount, 'after' => $afterCount, 'seed_artist' => $artistName]);
            } else {
                // Even if no user is authenticated, filter out seed artist tracks
                $beforeCount = count($formattedTracks);
                $formattedTracks = $this->filterBySeedArtist($formattedTracks, $artistName);
                $afterCount = count($formattedTracks);
                \Log::info("🎧 Applied seed artist filtering (no auth)", ['before' => $beforeCount, 'after' => $afterCount, 'seed_artist' => $artistName]);
            }

            // 9. Randomize order
            shuffle($formattedTracks);

            // 10. Limit results
            $formattedTracks = array_slice($formattedTracks, 0, $limit);

            // Debug: Count tracks by source
            $sourceCounts = [
                'spotify' => 0,
                'unknown' => 0
            ];

            foreach ($formattedTracks as $track) {
                $source = $track['source'] ?? 'unknown';
                if (isset($sourceCounts[$source])) {
                    $sourceCounts[$source]++;
                } else {
                    $sourceCounts['unknown']++;
                }
            }

            \Log::info("🎧 Final track source distribution", $sourceCounts);

            return response()->json([
                'success' => true,
                'data' => $formattedTracks,
                'total' => count($formattedTracks),
                'spotify_count' => count($spotifyTracks),
                'after_deduplication' => count($formattedTracks),
                'requested' => $limit,
                'source_debug' => $sourceCounts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get related tracks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get track key for a specific Spotify track ID
     * GET /api/music-discovery/track-key/{trackId}
     */
    public function getTrackKey(string $trackId): JsonResponse
    {
        try {
            // Simple endpoint to return track information
            // This might be used by the frontend for track verification
            return response()->json([
                'success' => true,
                'track_id' => $trackId,
                'message' => 'Track ID received successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process track key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for seed tracks using RapidAPI (replaces Spotify search)
     * POST /api/music-discovery/search-seed
     */
    public function searchSeedTracks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('query');
        $limit = min($request->input('limit', 20), 20); // Cap at 20

        \Log::info('🔍 [BACKEND] searchSeedTracks called', [
            'query' => $query,
            'limit' => $limit,
            'rapidapi_spotify_enabled' => RapidApiSpotifyService::enabled(),
            'has_rapidapi_spotify_service' => !!$this->rapidApiSpotifyService
        ]);

        // Try RapidAPI Spotify first (with automatic fallback to backup provider)
        if ($this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
            try {
                \Log::info('🔍 [BACKEND] Using RapidAPI Spotify for search', ['query' => $query, 'limit' => $limit]);
                $result = $this->rapidApiSpotifyService->searchTracks($query, $limit, 'tracks');

                // RapidAPI returns data.tracks[] not data.tracks.items[]
                $tracks = $result['data']['tracks'] ?? [];

                \Log::info('🔍 [BACKEND] RapidAPI Spotify search results', [
                    'success' => $result['success'] ?? false,
                    'has_data' => isset($result['data']),
                    'items_count' => count($tracks),
                    'structure' => isset($result['data']['tracks']['items']) ? 'official_spotify' : 'rapidapi'
                ]);

                if ($result['success'] && !empty($tracks)) {
                    $allTracks = $this->parseRawRapidApiTracks($tracks);

                    // Filter tracks to include keywords in metadata
                    $filteredTracks = array_filter($allTracks, function($track) use ($query) {
                        $queryWords = array_filter(explode(' ', strtolower(trim($query))));

                        // Get all searchable metadata
                        $artistNames = array_map(fn($artist) => strtolower($artist['name']), $track['artists']);
                        $trackTitle = strtolower($track['name']);
                        $allMetadata = implode(' ', array_merge($artistNames, [$trackTitle]));

                        // Check if each query word appears in the metadata
                        foreach ($queryWords as $word) {
                            if (str_contains($allMetadata, $word)) {
                                return true; // At least one keyword matches
                            }
                        }

                        return false; // No keywords found in metadata
                    });

                    // Remove duplicates (by normalized artist + title)
                    $dedupedTracks = $this->removeDuplicateTracks(array_values($filteredTracks));

                    \Log::info('🔍 [BACKEND] Filtered tracks by keyword inclusion', [
                        'original_count' => count($allTracks),
                        'filtered_count' => count($filteredTracks),
                        'after_dedup' => count($dedupedTracks),
                        'query' => $query,
                        'sample_tracks' => array_slice(array_map(fn($t) => $t['artist'] . ' - ' . $t['name'], $filteredTracks), 0, 5)
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => array_values($dedupedTracks) // Re-index array
                    ]);
                } else {
                    \Log::warning('🔍 [BACKEND] RapidAPI Spotify returned empty results or failed');
                }
            } catch (\Exception $e) {
                \Log::error('🔍 [BACKEND] RapidAPI Spotify search failed, falling back to Deezer', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            \Log::warning('🔍 [BACKEND] RapidAPI Spotify not enabled or service not available', [
                'rapidapi_spotify_enabled' => RapidApiSpotifyService::enabled(),
                'has_service' => !!$this->rapidApiSpotifyService
            ]);
        }

        // Fallback to Deezer search
        try {
            $response = Http::timeout(30)
                ->get('https://api.deezer.com/search', [
                    'q' => $query,
                    'limit' => $limit
                ]);

            if (!$response->successful()) {
                throw new \Exception("Deezer API error: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Format Deezer tracks to match expected format
            $tracks = array_map(function ($track) {
                return [
                    'id' => (string) $track['id'],
                    'name' => $track['title'],
                    'artist' => $track['artist']['name'] ?? 'Unknown Artist',
                    'album' => $track['album']['title'] ?? 'Unknown Album',
                    'duration_ms' => ($track['duration'] ?? 0) * 1000,
                    'external_url' => $track['link'] ?? null,
                    'preview_url' => $track['preview'] ?? null,
                    'image' => $track['album']['cover_medium'] ?? null,
                    'uri' => "deezer:track:{$track['id']}"
                ];
            }, $data['data'] ?? []);

            // Remove duplicates (by normalized artist + title)
            $tracks = $this->removeDuplicateTracks($tracks);

            return response()->json([
                'success' => true,
                'data' => $tracks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get music recommendations using SoundStats
     * POST /api/music-discovery/discover
     */
    public function discoverMusic(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seed_track_id' => 'required|string',
            'seed_track_name' => 'required|string',
            'seed_track_artist' => 'required|string',
            'parameters' => 'sometimes|array',
            'parameters.bpm_min' => 'sometimes|numeric|min:60|max:200',
            'parameters.bpm_max' => 'sometimes|numeric|min:60|max:200',
            'parameters.popularity' => 'sometimes|numeric|min:0|max:100',
            'parameters.key_compatibility' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $seedTrackId = $request->input('seed_track_id');
        $parameters = $request->input('parameters', []);
        $limit = $request->input('limct', 20);

        // Get recommendations from SoundStats
        $recommendations = $this->soundStatsService->getMixedRecommendations(
            [$seedTrackId],
            $parameters,
            $limit
        );

        $trackIds = $recommendations['track_ids'] ?? [];

        if (empty($trackIds)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Get full track details from Spotify
        $spotifyData = $this->spotifyService->batchGetTracks($trackIds);
        $tracks = $spotifyData['tracks'] ?? [];

        // Filter out null tracks (invalid IDs)
        $validTracks = array_filter($tracks, fn($track) => $track !== null);

        return response()->json([
            'success' => true,
            'data' => $this->formatSpotifyTracksArray($validTracks)
        ]);
    }

    public function getBatchTrackFeatures(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'track_ids' => 'required|array|min:1|max:50',
        'track_ids.*' => 'required|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $trackIds = $request->input('track_ids');
    $features = [];

    // Spotify allows batch requests for audio features
    try {
        $audioFeatures = $this->spotifyService->getBatchAudioFeatures($trackIds);
        
        foreach ($audioFeatures as $index => $feature) {
            if ($feature) {
                $features[$trackIds[$index]] = [
                    'bpm' => round($feature['tempo'] ?? 0),
                    'key' => $feature['key'] ?? null,
                    'mode' => $feature['mode'] ?? null,
                    'energy' => $feature['energy'] ?? null,
                    'danceability' => $feature['danceability'] ?? null,
                    'valence' => $feature['valence'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $features
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch batch features: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Get music recommendations using ReccoBeats
     * POST /api/music-discovery/discover-reccobeats
     */
    public function discoverMusicReccoBeats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seed_track_id' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:50',
            'acousticness' => 'sometimes|numeric|min:0|max:1',
            'danceability' => 'sometimes|numeric|min:0|max:1',
            'energy' => 'sometimes|numeric|min:0|max:1',
            'instrumentalness' => 'sometimes|numeric|min:0|max:1',
            'key' => 'sometimes|integer|min:-1|max:11',
            'liveness' => 'sometimes|numeric|min:0|max:1',
            'loudness' => 'sometimes|numeric|min:-60|max:2',
            'mode' => 'sometimes|integer|min:0|max:1',
            'speechiness' => 'sometimes|numeric|min:0|max:1',
            'tempo' => 'sometimes|numeric|min:0|max:250',
            'valence' => 'sometimes|numeric|min:0|max:1',
            'popularity' => 'sometimes|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $seedTrackId = $request->input('seed_track_id');
            $limit = $request->input('limit', 20);
            
            // Build query parameters
            $queryParams = [
                'seeds' => $seedTrackId,
                'size' => $limit,
            ];
            
            // Add audio feature parameters
            $features = ['acousticness', 'danceability', 'energy', 'instrumentalness', 'key', 'liveness', 'loudness', 'mode', 'speechiness', 'tempo', 'valence', 'popularity'];
            foreach ($features as $feature) {
                if ($request->has($feature)) {
                    $queryParams[$feature] = $request->input($feature);
                }
            }
            
            // Call ReccoBeats API
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout(30)
                ->get('https://api.reccobeats.com/v1/track/recommendation', $queryParams);
            
            if (!$response->successful()) {
                throw new \Exception("ReccoBeats API error: HTTP {$response->status()} - " . $response->body());
            }
            
            $data = $response->json();
            $trackIds = [];
            
            // Extract Spotify IDs from href URLs
            if (isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $track) {
                    if (isset($track['href'])) {
                        // Extract Spotify ID from URL: https://open.spotify.com/track/TRACK_ID
                        if (preg_match('/\/track\/([a-zA-Z0-9]+)/', $track['href'], $matches)) {
                            $trackIds[] = $matches[1];
                        }
                    }
                }
            }
            
            if (empty($trackIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No recommendations found from ReccoBeats'
                ]);
            }
            
            // Get track details from Spotify
            $tracks = [];
            
            foreach (array_slice($trackIds, 0, $limit) as $trackId) {
                try {
                    $track = $this->spotifyService->getTrackDetails($trackId);
                    if ($track) {
                        $tracks[] = $this->formatSingleSpotifyTrack($track);
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to fetch track {$trackId}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $tracks,
                'provider' => 'reccobeats',
                'total_found' => count($trackIds)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => "ReccoBeats discovery failed: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get music recommendations using RapidAPI Radio Workflow
     * POST /api/music-discovery/discover-rapidapi
     */
    public function discoverMusicRapidApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'seed_track_uri' => 'required|string',
            'max_popularity' => 'sometimes|integer|min:0|max:100',
            'apply_popularity_filter' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'exclude_track_ids' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $seedTrackUri = $request->input('seed_track_uri');
            $maxPopularity = $request->input('max_popularity', 100);
            $applyPopularityFilter = $request->input('apply_popularity_filter', false);
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $excludeTrackIds = $request->input('exclude_track_ids', []);

            // Get radio tracks without mandatory filtering
            $radioResult = $this->rapidApiService->createRadioPlaylist($seedTrackUri);
            if (!$radioResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $radioResult['error']
                ], 500);
            }

            // Get extra tracks to allow for deduplication and filtering
            $tracksResult = $this->rapidApiService->getPlaylistTracks($radioResult['playlist_id'], $limit + 50);
            if (!$tracksResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $tracksResult['error']
                ], 500);
            }

            $allTracks = $tracksResult['tracks'];
            
            // Remove duplicate tracks (by track ID and name combination)
            $uniqueTracks = [];
            $seenTracks = [];
            
            foreach ($allTracks as $track) {
                $trackKey = $track['id'] . '|' . strtolower($track['name'] ?? '');
                if (!in_array($trackKey, $seenTracks)) {
                    $seenTracks[] = $trackKey;
                    $uniqueTracks[] = $track;
                }
            }
            
            // Remove tracks that were already shown (for second+ calls)
            if (!empty($excludeTrackIds)) {
                $uniqueTracks = array_filter($uniqueTracks, function($track) use ($excludeTrackIds) {
                    return !in_array($track['id'], $excludeTrackIds);
                });
            }
            
            // Apply popularity filter only if requested
            $filteredTracks = $uniqueTracks;
            if ($applyPopularityFilter) {
                $filteredTracks = $this->rapidApiService->filterTracksByPopularity($uniqueTracks, $maxPopularity);
            }

            // Apply blacklist/saved filtering
            $userId = auth()->id();
            if ($userId) {
                $filteredTracks = $this->filterByUserPreferences($filteredTracks, $userId, null);
            }

            // Apply pagination after all filtering and deduplication
            $finalTracks = array_slice($filteredTracks, $offset, $limit);
            
            // Shuffle only if it's the first request (offset = 0)
            if ($offset === 0 && !empty($finalTracks)) {
                shuffle($finalTracks);
            }

            $result = [
                'success' => true,
                'tracks' => $finalTracks,
                'total_found' => count($allTracks),
                'after_filtering' => count($filteredTracks),
                'playlist_id' => $radioResult['playlist_id']
            ];

            \Log::info('RapidAPI Final Result', [
                'playlist_id' => $result['playlist_id'],
                'total_found' => $result['total_found'],
                'after_filtering' => $result['after_filtering'],
                'first_track' => $result['tracks'][0]['name'] ?? 'No tracks',
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatRapidApiTracksArray($result['tracks'] ?? []),
                'provider' => 'rapidapi',
                'total_found' => $result['total_found'],
                'after_filtering' => $result['after_filtering'],
                'playlist_id' => $result['playlist_id']
            ]);

        } catch (\Exception $e) {
            \Log::error('RapidAPI Discovery Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'RapidAPI discovery failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get track audio features (for displaying BPM, key, etc.)
     * GET /api/music-discovery/track-features/{trackId}
     */
    public function getTrackFeatures(string $trackId): JsonResponse
    {
        // Check if Spotify is enabled
        if (!$this->spotifyService::enabled()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'bpm' => 0,
                    'key' => null,
                    'mode' => null,
                    'energy' => null,
                    'danceability' => null,
                    'valence' => null,
                    'popularity' => null,
                ],
                'message' => 'Spotify not configured'
            ]);
        }

        $features = $this->spotifyService->getTrackAudioFeatures($trackId);

        if (!$features) {
            return response()->json([
                'success' => true,
                'data' => [
                    'bpm' => 0,
                    'key' => null,
                    'mode' => null,
                    'energy' => null,
                    'danceability' => null,
                    'valence' => null,
                    'popularity' => null,
                ],
                'message' => 'Track features not available'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bpm' => round($features['tempo'] ?? 0),
                'key' => $features['key'] ?? null,
                'mode' => $features['mode'] ?? null,
                'energy' => $features['energy'] ?? null,
                'danceability' => $features['danceability'] ?? null,
                'valence' => $features['valence'] ?? null,
                'popularity' => $features['popularity'] ?? null,
            ]
        ]);
    }

    /**
     * Filter tracks by user preferences (blacklist/saved) and seed artist
     */
    private function filterByUserPreferences(array $tracks, int $userId, string $seedArtist = null): array
    {
        try {
            // Check if blacklist tables exist - if not, return original tracks
            if (!Schema::hasTable('blacklisted_tracks') || 
                !Schema::hasTable('saved_tracks') || 
                !Schema::hasTable('blacklisted_artists')) {
                \Log::info('Blacklist tables not found, skipping filtering', [
                    'user_id' => $userId,
                    'track_count' => count($tracks)
                ]);
                return $tracks;
            }

            // Get user's blacklisted ISRCs, artist IDs, and artist names
            $blacklistedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);
            $savedIsrcs = SavedTrack::getSavedIsrcs($userId);
            $blacklistedArtistIds = BlacklistedArtist::getBlacklistedArtistIds($userId);
            
            // Also get blacklisted artist names for broader filtering
            $blacklistedArtistNames = [];
            try {
                $blacklistedArtists = BlacklistedArtist::where('user_id', $userId)->get();
                foreach ($blacklistedArtists as $artist) {
                    $blacklistedArtistNames[] = strtolower(trim($artist->artist_name));
                }
            } catch (\Exception $e) {
                \Log::warning('Could not fetch blacklisted artist names: ' . $e->getMessage());
            }

            return array_filter($tracks, function($track) use ($blacklistedIsrcs, $savedIsrcs, $blacklistedArtistIds, $blacklistedArtistNames, $seedArtist) {
            // Extract ISRC from track (may be nested in external_ids)
            $isrc = null;
            if (isset($track['external_ids']['isrc'])) {
                $isrc = $track['external_ids']['isrc'];
            } elseif (isset($track['isrc'])) {
                $isrc = $track['isrc'];
            }

            // Skip if no ISRC available
            if (!$isrc) {
                return true; // Keep track if we can't identify it
            }

            // Filter out blacklisted tracks by ISRC
            if (in_array($isrc, $blacklistedIsrcs)) {
                return false;
            }

            // Filter out saved tracks by ISRC (Spotify rule - no saved tracks in recommendations)
            if (in_array($isrc, $savedIsrcs)) {
                return false;
            }

            // Filter out tracks by blacklisted artists (primary artist = artists[0])
            $primaryArtistId = null;
            if (isset($track['artists'][0]['id'])) {
                $primaryArtistId = $track['artists'][0]['id'];
            } elseif (isset($track['artist_id'])) {
                $primaryArtistId = $track['artist_id'];
            }

            if ($primaryArtistId && in_array($primaryArtistId, $blacklistedArtistIds)) {
                return false;
            }

            // Also filter by artist names (for cases where we don't have artist IDs)
            $artistName = '';
            if (isset($track['artist'])) {
                $artistName = is_string($track['artist']) ? $track['artist'] : '';
            } elseif (isset($track['artist']['name'])) {
                $artistName = $track['artist']['name'];
            } elseif (isset($track['subtitle'])) {
                $artistName = $track['subtitle']; // Shazam format
            } elseif (isset($track['artists'][0]['name'])) {
                $artistName = $track['artists'][0]['name']; // Formatted structure
            }
            
            // Filter out tracks from seed artist by default
            if ($seedArtist && $artistName) {
                $normalizedArtistName = strtolower(trim($artistName));
                $normalizedSeedArtist = strtolower(trim($seedArtist));
                if ($normalizedArtistName === $normalizedSeedArtist) {
                    return false;
                }
            }

            if ($artistName && !empty($blacklistedArtistNames)) {
                $normalizedArtistName = strtolower(trim($artistName));
                if (in_array($normalizedArtistName, $blacklistedArtistNames)) {
                    return false;
                }
            }

                return true; // Keep track if it passes all filters
            });
        } catch (\Exception $e) {
            \Log::error('User preferences filtering error', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'track_count' => count($tracks)
            ]);
            
            // Return original tracks if filtering fails
            return $tracks;
        }
    }

    /**
     * Filter tracks by seed artist (for unauthenticated users)
     */
    private function filterBySeedArtist(array $tracks, string $seedArtist): array
    {
        if (!$seedArtist) {
            return $tracks;
        }

        $normalizedSeedArtist = strtolower(trim($seedArtist));

        return array_filter($tracks, function($track) use ($normalizedSeedArtist) {
            // Extract artist name from track
            $artistName = '';
            if (isset($track['artist'])) {
                $artistName = is_string($track['artist']) ? $track['artist'] : '';
            } elseif (isset($track['artist']['name'])) {
                $artistName = $track['artist']['name'];
            } elseif (isset($track['subtitle'])) {
                $artistName = $track['subtitle']; // Shazam format
            } elseif (isset($track['artists'][0]['name'])) {
                $artistName = $track['artists'][0]['name']; // Formatted structure
            }

            // Filter out tracks from seed artist
            if ($artistName) {
                $normalizedArtistName = strtolower(trim($artistName));
                if ($normalizedArtistName === $normalizedSeedArtist) {
                    return false;
                }
            }

            return true; // Keep track if not from seed artist
        });
    }

    /**
     * Format array of RapidAPI tracks for frontend
     */
    private function formatRapidApiTracksArray(array $tracks): array
    {
        return array_map(function ($track) {
            // Properly format artists array with IDs
            $artists = [];
            if (isset($track['artists']) && is_array($track['artists'])) {
                $artists = array_map(function($artist) {
                    return [
                        'id' => $artist['id'] ?? '',
                        'name' => $artist['name'] ?? 'Unknown Artist'
                    ];
                }, $track['artists']);
            } else {
                // Fallback if no proper artists array
                $artists = [
                    [
                        'id' => $track['artist_id'] ?? '',
                        'name' => $track['artist'] ?? 'Unknown Artist'
                    ]
                ];
            }

            return [
                'id' => $track['id'],
                'uri' => $track['uri'] ?? null,
                'name' => $track['name'],
                'artist' => $track['artist'],
                'artists' => $artists, // Proper artists array with IDs
                'album' => $track['album'],
                'album_image' => $track['image'],
                'image' => $track['image'],
                'duration_ms' => $track['duration_ms'] ?? 0,
                'duration' => $this->formatDuration($track['duration_ms'] ?? 0),
                'preview_url' => $track['preview_url'],
                'external_url' => $track['external_url'],
                'popularity' => $track['popularity'] ?? 0,
                'release_date' => $track['release_date'] ?? null,
                'explicit' => $track['explicit'] ?? false,
                'external_ids' => $track['external_ids'] ?? [], // Pass through external_ids (includes ISRC)
            ];
        }, $tracks);
    }

    /**
     * Format array of Spotify tracks for frontend (ORIGINAL METHOD - RENAMED)
     */
    private function formatSpotifyTracksArray(array $tracks): array
    {
        return array_map(function ($track) {
            return [
                'id' => $track['id'],
                'name' => $track['name'],
                'artist' => $track['artists'][0]['name'] ?? 'Unknown Artist',
                'artists' => array_map(fn($artist) => [
                    'id' => $artist['id'],
                    'name' => $artist['name']
                ], $track['artists'] ?? []),
                'album' => $track['album']['name'] ?? 'Unknown Album',
                'album_image' => $track['album']['images'][0]['url'] ?? null,
                'image' => $track['album']['images'][0]['url'] ?? null, // Add this for compatibility
                'duration_ms' => $track['duration_ms'] ?? 0,
                'duration' => $this->formatDuration($track['duration_ms'] ?? 0),
                'preview_url' => $track['preview_url'],
                'external_url' => $track['external_urls']['spotify'] ?? null,
                'popularity' => $track['popularity'] ?? 0,
                'release_date' => $track['album']['release_date'] ?? null,
                'label' => null, // Will be populated by enhanced metadata calls
                'followers' => null, // Will be populated by enhanced metadata calls
            ];
        }, $tracks);
    }

    /**
     * Parse raw RapidAPI search response and format for frontend
     * RapidAPI structure: data.tracks[].data.{id, name, artists.items[].profile.name, ...}
     */
    private function parseRawRapidApiTracks(array $tracks): array
    {
        return array_map(function ($trackWrapper) {
            // Each track is wrapped in a 'data' object
            $track = $trackWrapper['data'] ?? $trackWrapper;

            return [
                'id' => $track['id'] ?? '',
                'name' => $track['name'] ?? '',
                'artist' => $track['artists']['items'][0]['profile']['name'] ?? 'Unknown Artist',
                'artists' => array_map(fn($artist) => [
                    'id' => str_replace('spotify:artist:', '', $artist['uri'] ?? ''),
                    'name' => $artist['profile']['name'] ?? 'Unknown'
                ], $track['artists']['items'] ?? []),
                'album' => $track['albumOfTrack']['name'] ?? 'Unknown Album',
                'album_image' => $track['albumOfTrack']['coverArt']['sources'][0]['url'] ?? null,
                'image' => $track['albumOfTrack']['coverArt']['sources'][0]['url'] ?? null,
                'duration_ms' => $track['duration']['totalMilliseconds'] ?? 0,
                'duration' => $this->formatDuration($track['duration']['totalMilliseconds'] ?? 0),
                'preview_url' => null, // RapidAPI doesn't provide preview URLs
                'external_url' => "https://open.spotify.com/track/{$track['id']}" ?? null,
                'popularity' => 0, // Not available in search response
                'release_date' => null, // Not available in search response
                'label' => null,
                'followers' => null,
                'external_ids' => [], // Add ISRC if available
            ];
        }, $tracks);
    }

    /**
     * Format related tracks array to match frontend expectations
     */
    private function formatRelatedTracksArray(array $tracks): array
    {
        return array_map(function ($track) {
            $extractedImage = $this->extractTrackImage($track);
            $matchScore = $track['match'] ?? null;
            
            
            $result = [
                'id' => $track['id'] ?? $track['mbid'] ?? uniqid('track_', true),
                'name' => $track['title'] ?? $track['name'] ?? '', // Convert 'title' to 'name'
                'artist' => is_array($track['artist']) ? ($track['artist']['name'] ?? 'Unknown Artist') : ($track['artist'] ?? 'Unknown Artist'),
                'album' => isset($track['album']) ? 
                    (is_array($track['album']) ? ($track['album']['title'] ?? 'Unknown Album') : ($track['album'] ?? 'Unknown Album')) :
                    'Unknown Album', // Fallback for tracks without album info (like Last.fm)
                'duration_ms' => isset($track['duration']) ? $track['duration'] * 1000 : 0,
                'external_url' => $track['external_url'] ?? null,
                'preview_url' => $track['preview_url'] ?? null,
                'image' => $extractedImage,
                'uri' => isset($track['id']) ? "spotify:track:{$track['id']}" : null,
                'external_ids' => $track['external_ids'] ?? [], // Add external_ids for ISRC
                'artists' => isset($track['artist']['name']) ? [['id' => '', 'name' => $track['artist']['name']]] : [], // Add artists array
                'source' => $track['source'] ?? 'unknown', // PRESERVE SOURCE IDENTIFIER
                'match' => $matchScore, // PRESERVE MATCH SCORE FROM LAST.FM
                'shazam_id' => $track['shazam_id'] ?? null, // Preserve Shazam ID if available
                'spotify_id' => $track['spotify_id'] ?? null, // Preserve Spotify ID if available
                'popularity' => $track['popularity'] ?? null, // Add Spotify popularity
                'release_date' => $track['album']['release_date'] ?? $track['release_date'] ?? null // Add release date
            ];
            
            
            return $result;
        }, $tracks);
    }

    /**
     * Extract track image from various track formats
     */
    private function extractTrackImage(array $track): ?string
    {
        // Try different possible image locations
        if (isset($track['album']['images'][0]['url'])) {
            return $track['album']['images'][0]['url'];
        }
        
        if (isset($track['album']['images']) && is_array($track['album']['images']) && count($track['album']['images']) > 0) {
            return $track['album']['images'][0]['url'] ?? null;
        }
        
        if (isset($track['album']['cover'])) {
            return $track['album']['cover'];
        }
        
        if (isset($track['image'])) {
            // Handle Last.fm image array format
            if (is_array($track['image'])) {
                // Last.fm returns images in different sizes, prefer largest
                foreach (['extralarge', 'large', 'medium', 'small'] as $size) {
                    foreach ($track['image'] as $img) {
                        if (isset($img['size']) && $img['size'] === $size && !empty($img['#text'])) {
                            return $img['#text'];
                        }
                    }
                }
                // Fallback to first image with text
                foreach ($track['image'] as $img) {
                    if (!empty($img['#text'])) {
                        return $img['#text'];
                    }
                }
                return null;
            }
            // Handle string image URLs
            return $track['image'];
        }
        
        if (isset($track['album_image'])) {
            return $track['album_image'];
        }

        // For Spotify tracks that come directly
        if (isset($track['album_cover'])) {
            return $track['album_cover'];
        }
        
        return null;
    }
    
    /**
     * Format single Spotify track for ReccoBeats (NEW METHOD)
     */
    private function formatSingleSpotifyTrack(array $track): array
    {
        return [
            'id' => $track['id'],
            'name' => $track['name'],
            'artist' => $track['artists'][0]['name'] ?? 'Unknown Artist',
            'artists' => array_map(fn($artist) => ['id' => $artist['id'], 'name' => $artist['name']], $track['artists'] ?? []),
            'album' => $track['album']['name'] ?? 'Unknown Album',
            'preview_url' => $track['preview_url'],
            'external_urls' => $track['external_urls'],
            'duration_ms' => $track['duration_ms'],
            'popularity' => $track['popularity'] ?? 0,
            'release_date' => $track['album']['release_date'] ?? null,
            'image' => $track['album']['images'][0]['url'] ?? null,
            'label' => null, // Will be populated by enhanced metadata calls
            'followers' => null, // Will be populated by enhanced metadata calls
        ];
    }

    /**
     * Search Deezer tracks via backend proxy to avoid CORS
     * GET /api/music-discovery/search-deezer
     */
    public function searchDeezer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('q');
        $limit = $request->input('limit', 50);

        try {
            $response = Http::timeout(30)
                ->get('https://api.deezer.com/search', [
                    'q' => $query,
                    'limit' => $limit
                ]);

            if (!$response->successful()) {
                throw new \Exception("Deezer API error: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Format tracks for frontend
            $tracks = array_map(function ($track) {
                return [
                    'id' => $track['id'],
                    'title' => $track['title'],
                    'duration' => $track['duration'],
                    'artist' => [
                        'id' => $track['artist']['id'] ?? null,
                        'name' => $track['artist']['name'] ?? 'Unknown Artist'
                    ],
                    'album' => [
                        'id' => $track['album']['id'] ?? null,
                        'title' => $track['album']['title'] ?? 'Unknown Album',
                        'release_date' => $track['album']['release_date'] ?? null
                    ],
                    'source' => 'deezer'
                ];
            }, $data['data'] ?? []);

            return response()->json([
                'success' => true,
                'data' => $tracks,
                'total' => count($tracks)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Deezer search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for seed track on Spotify and get recommendations
     * POST /api/music-discovery/seed-recommendations
     */
    public function getSeedRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'artist_name' => 'required|string',
            'track_title' => 'required|string',
            'track_id' => 'required|string' // Deezer track ID for reference
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $artistName = $request->input('artist_name');
        $trackTitle = $request->input('track_title');
        $deezerTrackId = $request->input('track_id');

        try {
            // Step 1: Search Spotify for matching track
            $spotifyTrackId = $this->searchSpotifyTrack($artistName, $trackTitle);
            
            if (!$spotifyTrackId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No matching track found on Spotify'
                ], 404);
            }

            \Log::info("Found Spotify track: {$spotifyTrackId} for {$artistName} - {$trackTitle}");

            // Step 2: Get recommendations from multiple sources in parallel
            $recommendations = $this->getParallelRecommendations($spotifyTrackId, $artistName, $trackTitle);

            // Step 3: Process and filter results
            $processedTracks = $this->processRecommendations($recommendations, $artistName);

            return response()->json([
                'success' => true,
                'data' => $processedTracks,
                'total' => count($processedTracks),
                'seed_track' => [
                    'artist' => $artistName,
                    'title' => $trackTitle,
                    'deezer_id' => $deezerTrackId,
                    'spotify_id' => $spotifyTrackId
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Seed recommendations failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to get recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search Spotify for exact track match using RapidAPI
     */
    private function searchSpotifyTrack(string $artistName, string $trackTitle): ?string
    {
        try {
            // Use RapidAPI Spotify search since direct API has connectivity issues
            $query = "{$artistName} {$trackTitle}";
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                    'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
                ])
                ->get("https://spotify81.p.rapidapi.com/search", [
                    'q' => $query,
                    'type' => 'tracks',
                    'limit' => '10'
                ]);

            if (!$response->successful()) {
                \Log::warning("Spotify RapidAPI search failed: HTTP {$response->status()}");
                return null;
            }

            $data = $response->json();
            \Log::info("Spotify RapidAPI search response structure", ['data' => $data]);
            
            // Handle different possible response formats
            $tracks = $data['tracks']['items'] ?? $data['tracks'] ?? $data['items'] ?? [];

            // Find exact match
            foreach ($tracks as $track) {
                $spotifyArtist = $track['artists'][0]['name'] ?? $track['artist']['name'] ?? '';
                $spotifyTitle = $track['name'] ?? $track['title'] ?? '';
                
                if ($this->fuzzyMatch($artistName, $spotifyArtist) && 
                    $this->fuzzyMatch($trackTitle, $spotifyTitle)) {
                    $trackId = $track['id'] ?? $track['uri'] ?? null;
                    if ($trackId) {
                        // Extract ID from URI if needed
                        if (strpos($trackId, 'spotify:track:') === 0) {
                            $trackId = str_replace('spotify:track:', '', $trackId);
                        }
                        return $trackId;
                    }
                }
            }

            // If no exact match found but we have tracks, use the first one as fallback
            if (!empty($tracks)) {
                $firstTrack = $tracks[0];
                $trackId = $firstTrack['id'] ?? $firstTrack['uri'] ?? null;
                if ($trackId) {
                    if (strpos($trackId, 'spotify:track:') === 0) {
                        $trackId = str_replace('spotify:track:', '', $trackId);
                    }
                    \Log::info("Using fallback Spotify track", ['track_id' => $trackId, 'artist' => $artistName, 'title' => $trackTitle]);
                    return $trackId;
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Spotify track search failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get recommendations from multiple sources in parallel
     */
    private function getParallelRecommendations(string $spotifyTrackId, string $artistName, string $trackTitle): array
    {
        $results = [
            'spotify' => [],
            'shazam' => []
        ];

        // Parallel API calls using promises
        $spotifyPromise = Http::async()
            ->timeout(30)
            ->withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
            ])
            ->get("https://spotify81.p.rapidapi.com/seed_to_playlist", [
                'uri' => "spotify:track:{$spotifyTrackId}"
            ]);

        $shazamSearchPromise = Http::async()
            ->timeout(30)
            ->withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
            ])
            ->get("https://shazam-api6.p.rapidapi.com/shazam/search_track/", [
                'query' => "{$artistName} {$trackTitle}"
            ]);

        try {
            // Wait for both requests
            $spotifyResponse = $spotifyPromise->wait();
            $shazamSearchResponse = $shazamSearchPromise->wait();

            // Process Spotify seed_to_playlist response
            if ($spotifyResponse->successful()) {
                $spotifyData = $spotifyResponse->json();
                $playlistUri = $spotifyData['uri'] ?? null;
                
                if ($playlistUri && preg_match('/spotify:playlist:(.+)/', $playlistUri, $matches)) {
                    $playlistId = $matches[1];
                    $results['spotify'] = $this->getSpotifyPlaylistTracks($playlistId);
                }
            }

            // Process Shazam search response
            if ($shazamSearchResponse->successful()) {
                $shazamData = $shazamSearchResponse->json();
                $tracks = $shazamData['result']['tracks']['hits'] ?? [];
                
                if (!empty($tracks)) {
                    $shazamTrackId = $tracks[0]['key'] ?? null;
                    if ($shazamTrackId) {
                        $results['shazam'] = $this->getShazamRecommendations($shazamTrackId);
                    }
                }
            }

        } catch (\Exception $e) {
            \Log::error('Parallel recommendations failed: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Get Spotify playlist tracks via RapidAPI
     */
    private function getSpotifyPlaylistTracks(string $playlistId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                    'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
                ])
                ->get("https://spotify81.p.rapidapi.com/playlist_tracks", [
                    'id' => $playlistId,
                    'offset' => '0',
                    'limit' => '100'
                ]);

            if (!$response->successful()) {
                \Log::warning("Spotify playlist tracks failed: HTTP {$response->status()}");
                return [];
            }

            $data = $response->json();
            $items = $data['items'] ?? [];
            $tracks = [];

            foreach ($items as $item) {
                $track = $item['track'] ?? null;
                if (!$track || !isset($track['id'])) continue;

                $tracks[] = [
                    'id' => $track['id'],
                    'title' => $track['name'] ?? '',
                    'artist' => $track['artists'][0]['name'] ?? '',
                    'duration' => ($track['duration_ms'] ?? 0) / 1000,
                    'release_date' => $track['album']['release_date'] ?? null,
                    'source' => 'spotify',
                    'spotify_id' => $track['id']
                ];
            }

            return $tracks;

        } catch (\Exception $e) {
            \Log::error('Spotify playlist tracks failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Shazam recommendations via RapidAPI
     */
    private function getShazamRecommendations(string $trackId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                    'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
                ])
                ->get("https://shazam-api6.p.rapidapi.com/shazam/similar_tracks", [
                    'track_id' => $trackId,
                    'limit' => '100'
                ]);

            if (!$response->successful()) {
                \Log::warning("Shazam recommendations failed: HTTP {$response->status()}");
                return [];
            }

            $data = $response->json();
            $tracks = $data['result']['tracks'] ?? [];
            $results = [];

            foreach ($tracks as $track) {
                if (!isset($track['key'])) continue;

                $results[] = [
                    'id' => $track['key'],
                    'title' => $track['title'] ?? '',
                    'artist' => $track['subtitle'] ?? '',
                    'duration' => 0, // Shazam doesn't provide duration
                    'release_date' => null,
                    'source' => 'shazam',
                    'shazam_id' => $track['key']
                ];
            }

            return $results;

        } catch (\Exception $e) {
            \Log::error('Shazam recommendations failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process and filter recommendations
     */
    private function processRecommendations(array $recommendations, string $seedArtist): array
    {
        $allTracks = [];

        // Combine tracks from all sources
        foreach ($recommendations['spotify'] ?? [] as $track) {
            $allTracks[] = $track;
        }
        foreach ($recommendations['shazam'] ?? [] as $track) {
            $allTracks[] = $track;
        }

        // Remove duplicates by title and artist
        $allTracks = $this->removeDuplicateTracks($allTracks);

        // Remove tracks from same seed artist
        $allTracks = $this->filterSameArtistTracks($allTracks, $seedArtist);

        // Randomize order
        shuffle($allTracks);

        // Return first 100 tracks for caching (display first 20 + pagination)
        return array_slice($allTracks, 0, 100);
    }


    /**
     * Enhanced recommendations with parallel search and filtering
     * GET /api/music-discovery/deezer-recommendations
     */
    public function getDeezerRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'track_id' => 'required|string',
            'artist_name' => 'required|string',
            'track_title' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $trackId = $request->input('track_id');
        $seedArtistName = trim($request->input('artist_name'));
        $seedTrackTitle = trim($request->input('track_title'));
        $limit = $request->input('limit', 20);

        try {
            // Step 1: Get seed track details and verify match
            $trackResponse = Http::timeout(30)->get("https://api.deezer.com/track/{$trackId}");
            
            if (!$trackResponse->successful()) {
                throw new \Exception("Failed to fetch track details: HTTP {$trackResponse->status()}");
            }

            $track = $trackResponse->json();
            
            // Validate that the track details match the provided artist/title
            $actualArtist = $track['artist']['name'] ?? '';
            $actualTitle = $track['title'] ?? '';
            
            if (!$this->trackMatches($seedArtistName, $seedTrackTitle, $actualArtist, $actualTitle)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Track details do not match. Expected: "' . $seedTrackTitle . '" by "' . $seedArtistName . '", Found: "' . $actualTitle . '" by "' . $actualArtist . '"'
                ], 400);
            }

            $artistId = $track['artist']['id'] ?? null;
            $genreId = $track['album']['genre_id'] ?? null;

            if (!$artistId) {
                throw new \Exception("Track artist information not found");
            }

            // Step 2: Parallel search to find the same track on both platforms
            $parallelTrackIds = $this->findTrackOnBothPlatforms($seedArtistName, $seedTrackTitle);
            
            if (empty($parallelTrackIds)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Could not find matching track on both platforms for recommendations'
                ], 404);
            }

            // Step 3: Get recommendations from both platforms
            $recommendations = [];
            $targetCount = $limit * 4; // Fetch 4x more to account for filtering and duplicates

            // Get Deezer recommendations
            $deezerRecs = $this->getDeezerRecommendationsForTrack($artistId, $genreId, $targetCount / 2);
            $recommendations = array_merge($recommendations, $deezerRecs);

            // Get Spotify recommendations if we have Spotify track ID
            if (isset($parallelTrackIds['spotify'])) {
                $spotifyRecs = $this->getSpotifyRecommendationsForTrack($parallelTrackIds['spotify'], $targetCount / 2);
                $recommendations = array_merge($recommendations, $spotifyRecs);
            }

            // Step 4: Remove duplicates (by title + artist combination)
            $uniqueRecommendations = $this->removeDuplicateTracks($recommendations);

            // Step 5: Remove tracks from the same artist as seed
            $filteredRecommendations = $this->filterOutSeedArtistTracks($uniqueRecommendations, $seedArtistName);

            // Step 6: Remove the original seed track
            $filteredRecommendations = array_filter($filteredRecommendations, function($rec) use ($trackId, $seedTrackTitle, $seedArtistName) {
                // Remove by ID (for same platform) or by title+artist (cross-platform)
                if (isset($rec['id']) && $rec['id'] == $trackId) return false;
                return !$this->trackMatches($seedArtistName, $seedTrackTitle, 
                    $rec['artist']['name'] ?? $rec['artist'] ?? '', 
                    $rec['title'] ?? '');
            });

            // Step 7: Randomize order
            $shuffledRecommendations = array_values($filteredRecommendations);
            shuffle($shuffledRecommendations);

            // Step 8: Limit to requested number
            $finalRecommendations = array_slice($shuffledRecommendations, 0, $limit);
            $actualCount = count($finalRecommendations);

            return response()->json([
                'success' => true,
                'data' => $finalRecommendations,
                'total' => $actualCount,
                'requested' => $limit,
                'found_before_filtering' => count($recommendations),
                'after_deduplication' => count($uniqueRecommendations),
                'after_artist_filter' => count($filteredRecommendations),
                'parallel_track_ids' => $parallelTrackIds,
                'seed_track' => [
                    'id' => $track['id'],
                    'title' => $track['title'],
                    'artist' => $track['artist']['name'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Deezer recommendations failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Spotify access token using client credentials
     */
    private function getSpotifyAccessToken(): string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');
        
        if (!$clientId || !$clientSecret) {
            throw new \Exception('Spotify credentials not configured. Please set SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET in .env');
        }
        
        $response = Http::asForm()
            ->timeout(30)
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get Spotify access token: HTTP {$response->status()}");
        }

        $data = $response->json();
        return $data['access_token'] ?? throw new \Exception('No access token in Spotify response');
    }

    /**
     * Search Spotify tracks via backend proxy to avoid CORS
     * GET /api/music-discovery/search-spotify
     */
    public function searchSpotify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('q');
        $limit = $request->input('limit', 50);

        try {
            $accessToken = $this->getSpotifyAccessToken();

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])
                ->get('https://api.spotify.com/v1/search', [
                    'q' => $query,
                    'type' => 'track',
                    'limit' => $limit,
                ]);

            if (!$response->successful()) {
                throw new \Exception("Spotify API error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $tracks = $data['tracks']['items'] ?? [];

            // Format tracks to match our interface
            $formattedTracks = array_map(function ($track) {
                return [
                    'id' => $track['id'],
                    'title' => $track['name'],
                    'artist' => [
                        'id' => $track['artists'][0]['id'] ?? '',
                        'name' => $track['artists'][0]['name'] ?? 'Unknown Artist',
                    ],
                    'album' => [
                        'id' => $track['album']['id'] ?? '',
                        'title' => $track['album']['name'] ?? 'Unknown Album',
                        'cover' => $track['album']['images'][0]['url'] ?? null,
                    ],
                    'duration' => intval($track['duration_ms'] / 1000), // Convert to seconds
                    'preview_url' => $track['preview_url'],
                    'external_urls' => $track['external_urls'],
                    'spotify_id' => $track['id'],
                    'source' => 'spotify'
                ];
            }, $tracks);

            return response()->json([
                'success' => true,
                'data' => $formattedTracks,
                'total' => count($formattedTracks)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Spotify search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parallel search both Deezer and Spotify
     * GET /api/music-discovery/search-parallel
     */
    public function searchParallel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('q');
        $limit = $request->input('limit', 25); // 25 each = 50 total

        try {
            // Execute both searches in parallel using Promise::all equivalent
            $deezerPromise = Http::timeout(30)->get('https://api.deezer.com/search', [
                'q' => $query,
                'limit' => $limit
            ]);

            $accessToken = $this->getSpotifyAccessToken();
            $spotifyPromise = Http::timeout(30)
                ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
                ->get('https://api.spotify.com/v1/search', [
                    'q' => $query,
                    'type' => 'track',
                    'limit' => $limit,
                ]);

            // Process Deezer results
            $deezerTracks = [];
            if ($deezerPromise->successful()) {
                $deezerData = $deezerPromise->json();
                $deezerTracks = array_map(function ($track) {
                    return array_merge($track, ['source' => 'deezer']);
                }, $deezerData['data'] ?? []);
            }

            // Process Spotify results
            $spotifyTracks = [];
            if ($spotifyPromise->successful()) {
                $spotifyData = $spotifyPromise->json();
                $tracks = $spotifyData['tracks']['items'] ?? [];
                
                $spotifyTracks = array_map(function ($track) {
                    return [
                        'id' => $track['id'],
                        'title' => $track['name'],
                        'artist' => [
                            'id' => $track['artists'][0]['id'] ?? '',
                            'name' => $track['artists'][0]['name'] ?? 'Unknown Artist',
                        ],
                        'album' => [
                            'id' => $track['album']['id'] ?? '',
                            'title' => $track['album']['name'] ?? 'Unknown Album',
                            'cover' => $track['album']['images'][0]['url'] ?? null,
                        ],
                        'duration' => intval($track['duration_ms'] / 1000),
                        'preview_url' => $track['preview_url'],
                        'external_urls' => $track['external_urls'],
                        'spotify_id' => $track['id'],
                        'source' => 'spotify'
                    ];
                }, $tracks);
            }

            \Log::info('=== PARALLEL SEARCH RESULTS ===');
            \Log::info('Deezer tracks found: ' . count($deezerTracks));
            \Log::info('Spotify tracks found: ' . count($spotifyTracks));
            
            // Log first few tracks from each source for debugging
            foreach (array_slice($deezerTracks, 0, 3) as $i => $track) {
                \Log::info("Deezer #{$i}: \"{$track['title']}\" by \"{$track['artist']['name']}\"");
            }
            foreach (array_slice($spotifyTracks, 0, 3) as $i => $track) {
                \Log::info("Spotify #{$i}: \"{$track['title']}\" by \"{$track['artist']['name']}\"");
            }

            // Combine and interleave results (alternate between sources)
            $combinedTracks = [];
            $maxCount = max(count($deezerTracks), count($spotifyTracks));
            
            for ($i = 0; $i < $maxCount; $i++) {
                if (isset($deezerTracks[$i])) {
                    $combinedTracks[] = $deezerTracks[$i];
                }
                if (isset($spotifyTracks[$i])) {
                    $combinedTracks[] = $spotifyTracks[$i];
                }
            }
            
            \Log::info('Combined tracks (before dedup): ' . count($combinedTracks));
            
            // Remove duplicates from parallel search results
            $uniqueCombinedTracks = $this->removeDuplicateTracks($combinedTracks);
            
            \Log::info('Combined tracks (after dedup): ' . count($uniqueCombinedTracks));
            \Log::info('=== END PARALLEL SEARCH RESULTS ===');

            return response()->json([
                'success' => true,
                'data' => $uniqueCombinedTracks,
                'total' => count($uniqueCombinedTracks),
                'deezer_count' => count($deezerTracks),
                'spotify_count' => count($spotifyTracks),
                'before_dedup' => count($combinedTracks),
                'after_dedup' => count($uniqueCombinedTracks)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Parallel search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if track details match (fuzzy matching)
     */
    private function trackMatches(string $expectedArtist, string $expectedTitle, string $actualArtist, string $actualTitle): bool
    {
        // Normalize strings for comparison
        $normalizeString = function($str) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $str)));
        };

        $expectedArtistNorm = $normalizeString($expectedArtist);
        $expectedTitleNorm = $normalizeString($expectedTitle);
        $actualArtistNorm = $normalizeString($actualArtist);
        $actualTitleNorm = $normalizeString($actualTitle);

        // Check for exact matches or contains relationships
        $artistMatch = $expectedArtistNorm === $actualArtistNorm || 
                      strpos($actualArtistNorm, $expectedArtistNorm) !== false ||
                      strpos($expectedArtistNorm, $actualArtistNorm) !== false;

        $titleMatch = $expectedTitleNorm === $actualTitleNorm || 
                     strpos($actualTitleNorm, $expectedTitleNorm) !== false ||
                     strpos($expectedTitleNorm, $actualTitleNorm) !== false;

        return $artistMatch && $titleMatch;
    }

    /**
     * Find track on both platforms using parallel search
     */
    private function findTrackOnBothPlatforms(string $artistName, string $trackTitle): array
    {
        $searchQuery = $artistName . ' ' . $trackTitle;
        $trackIds = [];

        try {
            // Search Deezer
            $deezerResponse = Http::timeout(30)->get('https://api.deezer.com/search', [
                'q' => $searchQuery,
                'limit' => 10
            ]);

            if ($deezerResponse->successful()) {
                $deezerData = $deezerResponse->json();
                foreach ($deezerData['data'] ?? [] as $track) {
                    if ($this->trackMatches($artistName, $trackTitle, $track['artist']['name'] ?? '', $track['title'] ?? '')) {
                        $trackIds['deezer'] = $track['id'];
                        break;
                    }
                }
            }

            // Search Spotify
            $accessToken = $this->getSpotifyAccessToken();
            $spotifyResponse = Http::timeout(30)
                ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
                ->get('https://api.spotify.com/v1/search', [
                    'q' => $searchQuery,
                    'type' => 'track',
                    'limit' => 10,
                ]);

            if ($spotifyResponse->successful()) {
                $spotifyData = $spotifyResponse->json();
                foreach ($spotifyData['tracks']['items'] ?? [] as $track) {
                    if ($this->trackMatches($artistName, $trackTitle, $track['artists'][0]['name'] ?? '', $track['name'] ?? '')) {
                        $trackIds['spotify'] = $track['id'];
                        break;
                    }
                }
            }

        } catch (\Exception $e) {
            // Log error but don't fail completely
            \Log::warning('Failed to find track on platforms: ' . $e->getMessage());
        }

        return $trackIds;
    }

    /**
     * Get Deezer recommendations for a track
     */
    private function getDeezerRecommendationsForTrack(string $artistId, ?string $genreId, int $targetCount): array
    {
        $recommendations = [];

        try {
            // Get related artists
            $relatedResponse = Http::timeout(30)->get("https://api.deezer.com/artist/{$artistId}/related");
            
            if ($relatedResponse->successful()) {
                $relatedArtists = $relatedResponse->json()['data'] ?? [];
                
                foreach (array_slice($relatedArtists, 0, 8) as $artist) {
                    if (count($recommendations) >= $targetCount) break;
                    
                    $artistTracksResponse = Http::timeout(30)->get("https://api.deezer.com/artist/{$artist['id']}/top", [
                        'limit' => 8
                    ]);
                    
                    if ($artistTracksResponse->successful()) {
                        $artistTracks = $artistTracksResponse->json()['data'] ?? [];
                        foreach ($artistTracks as $track) {
                            $track['source'] = 'deezer';
                            $recommendations[] = $track;
                        }
                    }
                }
            }

            // Get genre-based recommendations if needed
            if ($genreId && count($recommendations) < $targetCount) {
                $genreResponse = Http::timeout(30)->get("https://api.deezer.com/genre/{$genreId}/artists", [
                    'limit' => 15
                ]);
                
                if ($genreResponse->successful()) {
                    $genreArtists = $genreResponse->json()['data'] ?? [];
                    
                    foreach (array_slice($genreArtists, 0, 5) as $artist) {
                        if (count($recommendations) >= $targetCount) break;
                        
                        $artistTracksResponse = Http::timeout(30)->get("https://api.deezer.com/artist/{$artist['id']}/top", [
                            'limit' => 5
                        ]);
                        
                        if ($artistTracksResponse->successful()) {
                            $artistTracks = $artistTracksResponse->json()['data'] ?? [];
                            foreach ($artistTracks as $track) {
                                $track['source'] = 'deezer';
                                $recommendations[] = $track;
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            \Log::warning('Failed to get Deezer recommendations: ' . $e->getMessage());
        }

        return $recommendations;
    }

    /**
     * Get Spotify recommendations for a track
     */
    private function getSpotifyRecommendationsForTrack(string $spotifyTrackId, int $targetCount): array
    {
        $recommendations = [];

        try {
            $accessToken = $this->getSpotifyAccessToken();
            
            // Use Spotify's recommendations endpoint
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
                ->get('https://api.spotify.com/v1/recommendations', [
                    'seed_tracks' => $spotifyTrackId,
                    'limit' => min($targetCount, 100), // Spotify limit is 100
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $tracks = $data['tracks'] ?? [];
                
                foreach ($tracks as $track) {
                    $recommendations[] = [
                        'id' => $track['id'],
                        'title' => $track['name'],
                        'artist' => [
                            'id' => $track['artists'][0]['id'] ?? '',
                            'name' => $track['artists'][0]['name'] ?? 'Unknown Artist',
                        ],
                        'album' => [
                            'id' => $track['album']['id'] ?? '',
                            'title' => $track['album']['name'] ?? 'Unknown Album',
                            'cover' => $track['album']['images'][0]['url'] ?? null,
                            'release_date' => $track['album']['release_date'] ?? null,
                        ],
                        'duration' => intval($track['duration_ms'] / 1000),
                        'preview_url' => $track['preview_url'],
                        'external_urls' => $track['external_urls'],
                        'spotify_id' => $track['id'],
                        'popularity' => $track['popularity'] ?? null,
                        'release_date' => $track['album']['release_date'] ?? null,
                        'source' => 'spotify'
                    ];
                }
            }

        } catch (\Exception $e) {
            \Log::warning('Failed to get Spotify recommendations: ' . $e->getMessage());
        }

        return $recommendations;
    }

    /**
     * Remove duplicate tracks based on artist + title combination with normalization
     */
    private function removeDuplicateTracks(array $tracks): array
    {
        $seen = [];
        $unique = [];
        
        \Log::info('=== DUPLICATE REMOVAL DEBUG ===');
        \Log::info('Total tracks before deduplication: ' . count($tracks));

        foreach ($tracks as $index => $track) {
            $artistName = $track['artist']['name'] ?? $track['artist'] ?? '';
            $title = $track['title'] ?? $track['name'] ?? ''; // Handle both 'title' (Last.fm) and 'name' (Spotify/Shazam)
            $source = $track['source'] ?? 'unknown';
            
            // Normalize both artist and title for better duplicate detection
            $normalizedArtist = $this->normalizeForDuplicateCheck($artistName);
            $normalizedTitle = $this->normalizeForDuplicateCheck($title);
            
            $key = $normalizedArtist . '|' . $normalizedTitle;
            
            \Log::info("Track #{$index}: \"{$title}\" by \"{$artistName}\" [{$source}]");
            \Log::info("  Normalized: \"{$normalizedTitle}\" by \"{$normalizedArtist}\"");
            \Log::info("  Key: {$key}");
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $track;
                \Log::info("  -> KEPT (first occurrence)");
            } else {
                \Log::info("  -> REMOVED (duplicate)");
            }
        }
        
        \Log::info('Total tracks after deduplication: ' . count($unique));
        \Log::info('=== END DUPLICATE REMOVAL DEBUG ===');

        return $unique;
    }

    /**
     * Normalize string for duplicate checking (more aggressive than track matching)
     */
    private function normalizeForDuplicateCheck(string $str): string
    {
        // Convert to lowercase and remove extra whitespace
        $normalized = strtolower(trim($str));
        
        // Remove common variations that cause duplicates
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized); // Remove all punctuation and special chars
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Collapse multiple spaces to single space
        $normalized = preg_replace('/\b(feat|featuring|ft|with|vs|versus|and|&)\b.*$/i', '', $normalized); // Remove featuring parts
        $normalized = preg_replace('/\b(remix|remaster|remastered|radio edit|extended|version|mix)\b.*$/i', '', $normalized); // Remove version info
        $normalized = trim($normalized);
        
        return $normalized;
    }

    /**
     * Normalize string for track matching (less aggressive than duplicate checking)
     */
    private function normalizeForMatching(string $str): string
    {
        // Convert to lowercase and remove extra whitespace
        $normalized = strtolower(trim($str));
        
        // Keep ampersands but also create fallback matching
        // Remove other punctuation but keep structure for better matching
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-&]/u', '', $normalized); // Keep hyphens and ampersands
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Collapse multiple spaces
        $normalized = trim($normalized);
        
        return $normalized;
    }

    /**
     * Fuzzy match two normalized strings with ampersand handling
     */
    private function fuzzyMatch(string $str1, string $str2, float $threshold = 0.8): bool
    {
        if ($str1 === $str2) {
            return true;
        }
        
        // Try matching with ampersand variations
        $str1Alt = str_replace('&', 'and', $str1);
        $str2Alt = str_replace('&', 'and', $str2);
        
        if ($str1 === $str2Alt || $str1Alt === $str2 || $str1Alt === $str2Alt) {
            return true;
        }
        
        // Check if one string contains the other (original or ampersand variations)
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false ||
            strpos($str1Alt, $str2) !== false || strpos($str1, $str2Alt) !== false) {
            return true;
        }
        
        // Simple similarity check - use the best match among variations
        $similarity1 = 0;
        similar_text($str1, $str2, $similarity1);
        
        $similarity2 = 0;
        similar_text($str1Alt, $str2Alt, $similarity2);
        
        $bestSimilarity = max($similarity1, $similarity2);
        
        return ($bestSimilarity / 100) >= $threshold;
    }

    /**
     * Filter out tracks from the same artist as seed
     */
    private function filterOutSeedArtistTracks(array $tracks, string $seedArtistName): array
    {
        $seedArtistNorm = strtolower(trim($seedArtistName));
        
        return array_filter($tracks, function($track) use ($seedArtistNorm) {
            $trackArtist = $track['artist']['name'] ?? $track['artist'] ?? '';
            $trackArtistNorm = strtolower(trim($trackArtist));
            
            // Don't include tracks from the same artist (fuzzy match)
            return $trackArtistNorm !== $seedArtistNorm && 
                   strpos($trackArtistNorm, $seedArtistNorm) === false &&
                   strpos($seedArtistNorm, $trackArtistNorm) === false;
        });
    }

    /**
     * Format duration from milliseconds to MM:SS
     */
    private function formatDuration(int $durationMs): string
    {
        $seconds = round($durationMs / 1000);
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Calculate similarity between two strings using robust matching
     * Returns a score between 0 (no match) and 1 (perfect match)
     */
    private function calculateSimilarity(string $search, string $target): float
    {
        if (empty($search) || empty($target)) {
            return 0.0;
        }
        
        // Normalize both strings: remove extra spaces, trim, lowercase
        $search = trim(preg_replace('/\s+/', ' ', strtolower($search)));
        $target = trim(preg_replace('/\s+/', ' ', strtolower($target)));
        
        if (empty($search) || empty($target)) {
            return 0.0;
        }
        
        // Exact match gets perfect score
        if ($search === $target) {
            return 1.0;
        }
        
        // Substring match gets high score
        if (str_contains($target, $search) || str_contains($search, $target)) {
            return 0.95; // High score for substring matches
        }
        
        // Word-based matching (most important for partial queries)
        $searchWords = array_filter(explode(' ', $search));
        $targetWords = array_filter(explode(' ', $target));
        
        if (empty($searchWords) || empty($targetWords)) {
            return 0.0;
        }
        
        $matchingWords = 0;
        $searchWordCount = count($searchWords);
        
        foreach ($searchWords as $searchWord) {
            foreach ($targetWords as $targetWord) {
                // Check for exact word match or substring match
                if ($searchWord === $targetWord || 
                    str_contains($targetWord, $searchWord) || 
                    str_contains($searchWord, $targetWord)) {
                    $matchingWords++;
                    break; // Move to next search word
                }
            }
        }
        
        // Calculate word match percentage based on search words
        $wordMatchPercentage = $matchingWords / $searchWordCount;
        
        // If most search words match, give high score
        if ($wordMatchPercentage >= 0.8) {
            return 0.9;
        } else if ($wordMatchPercentage >= 0.6) {
            return 0.8;
        } else if ($wordMatchPercentage >= 0.4) {
            return 0.7;
        } else if ($wordMatchPercentage > 0) {
            return 0.6;
        }
        
        return 0.0; // No meaningful match
    }

    /**
     * Search for a track on Spotify using artist and title
     */
    private function searchSpotifyForTrack(string $artistName, string $trackTitle): ?array
    {
        try {
            $accessToken = $this->getSpotifyAccessToken();
            $query = "$artistName $trackTitle";
            
            \Log::info("🔍 searchSpotifyForTrack DEBUG", [
                'raw_artist' => $artistName,
                'raw_title' => $trackTitle,
                'raw_query' => $query,
                'encoded_query' => urlencode($query)
            ]);
            
            \Log::info("🔥 API_REQUEST_SPOTIFY_SEARCH", [
                'endpoint' => 'https://api.spotify.com/v1/search',
                'query' => $query,
                'type' => 'track',
                'limit' => 20,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->get("https://api.spotify.com/v1/search", [
                'q' => $query, // Don't double-encode - Http::get() will handle it
                'type' => 'track',
                'limit' => 20 // Increase limit to find more matches
            ]);

            \Log::info("🔥 API_RESPONSE_SPOTIFY_SEARCH", [
                'status' => $response->status(),
                'success' => $response->successful(),
                'result_count' => $response->json('tracks.items') ? count($response->json('tracks.items')) : 0,
                'timestamp' => now()->toISOString()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                \Log::info("🔍 searchSpotifyForTrack RESPONSE", [
                    'total_tracks' => $data['tracks']['total'] ?? 0,
                    'returned_tracks' => count($data['tracks']['items'] ?? []),
                    'first_3_tracks' => array_slice(array_map(function($track) {
                        return [
                            'id' => $track['id'],
                            'artist' => $track['artists'][0]['name'] ?? 'Unknown',
                            'title' => $track['name'],
                            'popularity' => $track['popularity'] ?? 0
                        ];
                    }, $data['tracks']['items'] ?? []), 0, 3)
                ]);
                return $data;
            }
        } catch (\Exception $e) {
            \Log::warning("Spotify search failed: " . $e->getMessage());
        }
        
        return null;
    }


    /**
     * Get related tracks from Spotify using the RapidAPI Spotify endpoint
     */
    private function getSpotifyRelatedTracks(string $spotifyTrackId, int $limit, ?array $seedTrack = null): array
    {
        try {
            // Step 1: Get playlist from track using RapidAPI
            \Log::info("🎧 Calling RapidAPI seed_to_playlist", [
                'spotify_track_id' => $spotifyTrackId,
                'uri' => "spotify:track:$spotifyTrackId"
            ]);
            
            \Log::info("🔥 API_REQUEST_RAPIDAPI_SPOTIFY_SEED_TO_PLAYLIST", [
                'endpoint' => 'https://spotify81.p.rapidapi.com/seed_to_playlist',
                'uri' => "spotify:track:$spotifyTrackId",
                'timestamp' => now()->toISOString()
            ]);

            $playlistResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
            ])->get("https://spotify81.p.rapidapi.com/seed_to_playlist", [
                'uri' => "spotify:track:$spotifyTrackId"
            ]);

            \Log::info("🔥 API_RESPONSE_RAPIDAPI_SPOTIFY_SEED_TO_PLAYLIST", [
                'status' => $playlistResponse->status(),
                'success' => $playlistResponse->successful(),
                'timestamp' => now()->toISOString()
            ]);

            \Log::info("🎧 RapidAPI seed_to_playlist response", [
                'status' => $playlistResponse->status(),
                'successful' => $playlistResponse->successful(),
                'body' => $playlistResponse->body()
            ]);

            if (!$playlistResponse->successful()) {
                \Log::warning("Failed to get playlist from Spotify track: " . $playlistResponse->body());
                return [];
            }

            $playlistData = $playlistResponse->json();
            // RapidAPI returns the playlist URI in mediaItems array
            $playlistUri = null;
            if (isset($playlistData['mediaItems'][0]['uri'])) {
                $playlistUri = $playlistData['mediaItems'][0]['uri'];
            } elseif (isset($playlistData['uri'])) {
                $playlistUri = $playlistData['uri'];
            }

            \Log::info("🎧 Extracted playlist URI", [
                'raw_response' => $playlistData,
                'extracted_uri' => $playlistUri
            ]);

            if (!$playlistUri || !preg_match('/spotify:playlist:(\w+)/', $playlistUri, $matches)) {
                \Log::warning("Invalid playlist URI from Spotify: $playlistUri");
                return [];
            }

            $playlistId = $matches[1];

            // Step 2: Get tracks from the playlist
            \Log::info("🔥 API_REQUEST_RAPIDAPI_SPOTIFY_PLAYLIST_TRACKS", [
                'endpoint' => 'https://spotify81.p.rapidapi.com/playlist_tracks',
                'playlist_id' => $playlistId,
                'limit' => min($limit, 100),
                'timestamp' => now()->toISOString()
            ]);

            $tracksResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
            ])->get("https://spotify81.p.rapidapi.com/playlist_tracks", [
                'id' => $playlistId,
                'offset' => 0,
                'limit' => min($limit, 100)
            ]);

            \Log::info("🔥 API_RESPONSE_RAPIDAPI_SPOTIFY_PLAYLIST_TRACKS", [
                'status' => $tracksResponse->status(),
                'success' => $tracksResponse->successful(),
                'timestamp' => now()->toISOString()
            ]);

            if (!$tracksResponse->successful()) {
                \Log::warning("Failed to get tracks from Spotify playlist: " . $tracksResponse->body());
                return [];
            }

            $tracksData = $tracksResponse->json();
            $tracks = [];

            if (isset($tracksData['items']) && is_array($tracksData['items'])) {
                foreach ($tracksData['items'] as $item) {
                    if (isset($item['track'])) {
                        $track = $item['track'];
                        $tracks[] = [
                            'id' => $track['id'] ?? null,
                            'title' => $track['name'] ?? 'Unknown Title',
                            'duration' => round(($track['duration_ms'] ?? 0) / 1000),
                            'artist' => [
                                'name' => $track['artists'][0]['name'] ?? 'Unknown Artist'
                            ],
                            'album' => [
                                'title' => $track['album']['name'] ?? 'Unknown Album',
                                'release_date' => $track['album']['release_date'] ?? null,
                                'images' => $track['album']['images'] ?? []
                            ],
                            'external_url' => $track['external_urls']['spotify'] ?? null,
                            'preview_url' => $track['preview_url'] ?? null,
                            'popularity' => $track['popularity'] ?? null,
                            'release_date' => $track['album']['release_date'] ?? null,
                            'source' => 'spotify'
                        ];
                    }
                }
            }

            // Apply genre-aware filtering to remove mainstream tracks for underground music
            if ($seedTrack) {
                $tracks = $this->filterMainstreamTracks($tracks, $seedTrack);
            }

            return $tracks;

        } catch (\Exception $e) {
            \Log::warning("Spotify related tracks failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Filter out mainstream commercial tracks when seed track is from underground genres
     */
    private function filterMainstreamTracks(array $tracks, array $seedTrack): array
    {
        // Get seed track popularity and artist info
        $seedPopularity = $seedTrack['popularity'] ?? 50;
        $seedArtist = $seedTrack['artists'][0]['name'] ?? '';
        
        \Log::info("🎧 Genre-aware filtering", [
            'seed_artist' => $seedArtist,
            'seed_popularity' => $seedPopularity,
            'total_tracks_before_filter' => count($tracks)
        ]);
        
        // If seed track is underground (low popularity), filter out very popular tracks
        if ($seedPopularity < 30) {
            \Log::info("🎧 Underground track detected - filtering mainstream tracks", [
                'seed_popularity' => $seedPopularity
            ]);
            
            // Define mainstream artists to filter out for underground seed tracks
            $mainstreamArtists = [
                'demi lovato', 'the weeknd', 'ariana grande', 'taylor swift', 'drake',
                'justin bieber', 'ed sheeran', 'bruno mars', 'rihanna', 'beyonce',
                'kanye west', 'post malone', 'billie eilish', 'dua lipa', 'harry styles',
                'selena gomez', 'shawn mendes', 'maroon 5', 'imagine dragons',
                'coldplay', 'calvin harris', 'david guetta', 'martin garrix'
            ];
            
            $filteredTracks = [];
            foreach ($tracks as $track) {
                $trackArtist = strtolower($track['artist']['name'] ?? '');
                
                // Skip very mainstream artists for underground seed tracks
                if (in_array($trackArtist, $mainstreamArtists)) {
                    \Log::debug("🎧 Filtered out mainstream artist: {$track['artist']['name']}");
                    continue;
                }
                
                $filteredTracks[] = $track;
            }
            
            \Log::info("🎧 Mainstream filtering completed", [
                'tracks_before' => count($tracks),
                'tracks_after' => count($filteredTracks),
                'filtered_out' => count($tracks) - count($filteredTracks)
            ]);
            
            return $filteredTracks;
        }
        
        // For popular seed tracks, return all recommendations
        return $tracks;
    }

    /**
     * Get related tracks from Shazam using RapidAPI
     */
    private function getShazamRelatedTracks(string $artistName, string $trackTitle, int $limit): array
    {
        try {
            // Step 1: Search for the track on Shazam to get its ID
            // Try multiple search strategies to improve success rate for underground tracks
            \Log::info("🎵 Calling Shazam search (multiple strategies)", [
                'artist' => $artistName,
                'title' => $trackTitle,
                'strategy' => 'Try full query first, then artist-only if needed'
            ]);
            
            $searchResponse = null;
            $shazamTrackId = null;
            
            // Strategy 1: Search by full artist + track query (better for underground tracks)
            $fullQuery = "$artistName $trackTitle";
            \Log::info("🎵 Shazam Strategy 1: Full query", ['query' => $fullQuery]);
            
            \Log::info("🔥 API_REQUEST_SHAZAM_SEARCH_TRACK", [
                'endpoint' => 'https://shazam-api6.p.rapidapi.com/shazam/search_track/',
                'query' => $fullQuery,
                'limit' => 15,
                'strategy' => 'full_query',
                'timestamp' => now()->toISOString()
            ]);

            $searchResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
            ])->get("https://shazam-api6.p.rapidapi.com/shazam/search_track/", [
                'query' => $fullQuery,
                'limit' => 15 // Increase limit for better success rate
            ]);

            \Log::info("🔥 API_RESPONSE_SHAZAM_SEARCH_TRACK", [
                'status' => $searchResponse->status(),
                'success' => $searchResponse->successful(),
                'strategy' => 'full_query',
                'timestamp' => now()->toISOString()
            ]);

            if ($searchResponse && $searchResponse->successful()) {
                $searchData = $searchResponse->json();

                // Check if API returned an error even with 200 status
                if (isset($searchData['status']) && $searchData['status'] === false) {
                    \Log::error("🎵 ❌ SHAZAM API ERROR (HTTP 200 but error response)", [
                        'strategy' => 'full_query',
                        'error_message' => $searchData['message'] ?? 'Unknown error',
                        'full_response' => $searchData,
                        'headers' => $searchResponse->headers(),
                        'possible_causes' => [
                            'Invalid API key',
                            'Subscription expired',
                            'API provider issue',
                            'Rate limit (soft limit)'
                        ]
                    ]);
                } else {
                    $shazamTrackId = $this->findMatchingShazamTrack($searchData, $artistName, $trackTitle);
                }
            }
            
            // Strategy 2: If full query failed, try artist name only (original strategy)
            if (!$shazamTrackId) {
                \Log::info("🎵 Shazam Strategy 2: Artist name only (fallback)", ['query' => $artistName]);
                
                \Log::info("🔥 API_REQUEST_SHAZAM_SEARCH_TRACK", [
                    'endpoint' => 'https://shazam-api6.p.rapidapi.com/shazam/search_track/',
                    'query' => $artistName,
                    'limit' => 15,
                    'strategy' => 'artist_only_fallback',
                    'timestamp' => now()->toISOString()
                ]);

                $searchResponse = Http::withHeaders([
                    'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                    'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
                ])->get("https://shazam-api6.p.rapidapi.com/shazam/search_track/", [
                    'query' => $artistName,
                    'limit' => 15 // Increase limit for better success rate
                ]);

                \Log::info("🔥 API_RESPONSE_SHAZAM_SEARCH_TRACK", [
                    'status' => $searchResponse->status(),
                    'success' => $searchResponse->successful(),
                    'strategy' => 'artist_only_fallback',
                    'timestamp' => now()->toISOString()
                ]);

                if ($searchResponse && $searchResponse->successful()) {
                    $searchData = $searchResponse->json();

                    // Check if API returned an error even with 200 status
                    if (isset($searchData['status']) && $searchData['status'] === false) {
                        \Log::error("🎵 ❌ SHAZAM API ERROR (HTTP 200 but error response)", [
                            'strategy' => 'artist_only_fallback',
                            'error_message' => $searchData['message'] ?? 'Unknown error',
                            'full_response' => $searchData,
                            'headers' => $searchResponse->headers(),
                            'possible_causes' => [
                                'Invalid API key',
                                'Subscription expired',
                                'API provider issue',
                                'Rate limit (soft limit)'
                            ]
                        ]);
                    }
                }
            }

            \Log::info("🎵 Shazam search response", [
                'status' => $searchResponse->status(),
                'successful' => $searchResponse->successful(),
                'body_preview' => substr($searchResponse->body(), 0, 500) . '...',
                'content_type' => $searchResponse->header('Content-Type'),
                'has_result_section' => isset($searchResponse->json()['result']) ? 'yes' : 'no',
                'has_tracks_section' => isset($searchResponse->json()['result']['tracks']) ? 'yes' : 'no',
                'has_hits_section' => isset($searchResponse->json()['result']['tracks']['hits']) ? 'yes' : 'no',
                'total_hits' => count($searchResponse->json()['result']['tracks']['hits'] ?? [])
            ]);

            if (!$searchResponse->successful()) {
                $responseBody = $searchResponse->body();
                if ($searchResponse->status() === 429) {
                    \Log::warning("🎵 ❌ SHAZAM API QUOTA EXCEEDED", [
                        'status' => $searchResponse->status(),
                        'message' => 'Monthly quota exceeded for Shazam API',
                        'body' => $responseBody
                    ]);
                } else {
                    \Log::warning("🎵 ❌ SHAZAM SEARCH FAILED", [
                        'status' => $searchResponse->status(),
                        'body' => $responseBody
                    ]);
                }
                return [];
            }

            // If we haven't found a track ID from Strategy 1, try Strategy 2 response
            if (!$shazamTrackId) {
                $searchData = $searchResponse->json();
                $shazamTrackId = $this->findMatchingShazamTrack($searchData, $artistName, $trackTitle);
            }

            if (!$shazamTrackId) {
                \Log::warning("🎵 ❌ SHAZAM SEARCH FAILED - No matching track found", [
                    'searched_artist' => $artistName,
                    'searched_title' => $trackTitle,
                    'search_strategy' => 'Artist name only, then match by title',
                    'reason' => 'Either no tracks found for artist OR no track titles matched',
                    'next_steps' => 'Try searching on Shazam website to verify if track exists'
                ]);
                return [];
            }

            // Step 2: Get related tracks using the Shazam track ID
            \Log::info("🎵 Getting Shazam recommendations", [
                'shazam_track_id' => $shazamTrackId,
                'limit_requested' => $limit,
                'limit_actual' => min($limit, 50),
                'max_limit' => 50
            ]);
            
            \Log::info("🔥 API_REQUEST_SHAZAM_SIMILAR_TRACKS", [
                'endpoint' => 'https://shazam-api6.p.rapidapi.com/shazam/similar_tracks',
                'track_id' => $shazamTrackId,
                'limit' => min($limit, 50),
                'timestamp' => now()->toISOString()
            ]);

            $relatedResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
            ])->get("https://shazam-api6.p.rapidapi.com/shazam/similar_tracks", [
                'track_id' => $shazamTrackId,
                'limit' => min($limit, 50) // Limit to maximum 50 tracks
            ]);

            \Log::info("🔥 API_RESPONSE_SHAZAM_SIMILAR_TRACKS", [
                'status' => $relatedResponse->status(),
                'success' => $relatedResponse->successful(),
                'timestamp' => now()->toISOString()
            ]);

            if (!$relatedResponse->successful()) {
                \Log::warning("🎵 ❌ Shazam recommendations API failed", [
                    'status' => $relatedResponse->status(),
                    'body' => $relatedResponse->body(),
                    'shazam_track_id' => $shazamTrackId
                ]);
                return [];
            }

            $relatedData = $relatedResponse->json();

            // Check if API returned an error even with 200 status
            if (isset($relatedData['status']) && $relatedData['status'] === false) {
                \Log::error("🎵 ❌ SHAZAM RECOMMENDATIONS API ERROR (HTTP 200 but error response)", [
                    'track_id' => $shazamTrackId,
                    'error_message' => $relatedData['message'] ?? 'Unknown error',
                    'full_response' => $relatedData,
                    'headers' => $relatedResponse->headers(),
                    'possible_causes' => [
                        'Invalid API key',
                        'Subscription expired',
                        'API provider issue',
                        'Rate limit (soft limit)'
                    ]
                ]);
                return [];
            }

            $tracks = [];

            \Log::info("🎵 Shazam recommendations response", [
                'status' => $relatedResponse->status(),
                'tracks_count' => isset($relatedData['result']['tracks']) ? count($relatedData['result']['tracks']) : 0
            ]);

            if (isset($relatedData['result']['tracks']) && is_array($relatedData['result']['tracks'])) {
                foreach ($relatedData['result']['tracks'] as $index => $track) {
                    
                    $tracks[] = [
                        'id' => $track['key'] ?? null,
                        'key' => $track['key'] ?? null,
                        'title' => $track['title'] ?? 'Unknown Title',
                        'duration' => 180, // Shazam doesn't provide duration, use default
                        'artist' => [
                            'name' => $track['subtitle'] ?? 'Unknown Artist'
                        ],
                        'subtitle' => $track['subtitle'] ?? 'Unknown Artist',
                        'album' => [
                            'title' => 'Unknown Album',
                            'release_date' => null
                        ],
                        'source' => 'shazam'
                    ];
                }
            }

            // If no recommendations found, try fallback with artist's most popular track
            if (empty($tracks)) {
                \Log::info("🎵 🔄 SHAZAM FALLBACK: Trying artist's most popular track", [
                    'original_track_id' => $shazamTrackId,
                    'artist' => $artistName,
                    'reason' => 'Original track returned 0 recommendations'
                ]);
                
                $fallbackTracks = $this->tryShazamArtistFallback($artistName, $trackTitle, $limit);
                $tracks = array_merge($tracks, $fallbackTracks);
            }

            \Log::info("🎵 ✅ SHAZAM RECOMMENDATIONS COMPLETE", [
                'seed_track_id' => $shazamTrackId,
                'total_tracks_collected' => count($tracks),
                'limit_requested' => $limit,
                'limit_applied' => min($limit, 50),
                'tracks_under_limit' => count($tracks) <= 50,
                'used_fallback' => empty($relatedData['result']['tracks'])
            ]);
            
            return $tracks;

        } catch (\Exception $e) {
            \Log::warning("Shazam related tracks failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify if a track matches the search criteria
     */
    private function verifyTrackMatch(string $searchArtist, string $searchTitle, array $track): bool
    {
        $trackArtist = '';
        $trackTitle = '';

        // Extract track title from different formats (Shazam vs Spotify)
        if (isset($track['heading']['title'])) {
            // Shazam format
            $trackTitle = $track['heading']['title'];
        } elseif (isset($track['name'])) {
            // Spotify format
            $trackTitle = $track['name'];
        } elseif (isset($track['title'])) {
            // Generic format
            $trackTitle = $track['title'];
        }

        // Extract artist name from different formats (Shazam vs Spotify)
        if (isset($track['heading']['subtitle'])) {
            // Shazam format
            $trackArtist = $track['heading']['subtitle'];
        } elseif (isset($track['artists'][0]['name'])) {
            // Spotify format
            $trackArtist = $track['artists'][0]['name'];
        } elseif (isset($track['subtitle'])) {
            // Generic format
            $trackArtist = $track['subtitle'];
        }

        // Normalize strings for comparison
        $searchArtistNorm = $this->normalizeForMatching($searchArtist);
        $searchTitleNorm = $this->normalizeForMatching($searchTitle);
        $trackArtistNorm = $this->normalizeForMatching($trackArtist);
        $trackTitleNorm = $this->normalizeForMatching($trackTitle);

        \Log::info("🎵 Track matching details", [
            'search_artist' => $searchArtist,
            'search_title' => $searchTitle,
            'track_artist' => $trackArtist,
            'track_title' => $trackTitle,
            'search_artist_norm' => $searchArtistNorm,
            'search_title_norm' => $searchTitleNorm,
            'track_artist_norm' => $trackArtistNorm,
            'track_title_norm' => $trackTitleNorm
        ]);

        // Strategy 1: Exact match after normalization
        if ($searchArtistNorm === $trackArtistNorm && $searchTitleNorm === $trackTitleNorm) {
            \Log::info("🎵 Match found: Exact match");
            return true;
        }

        // Strategy 2: Artist exact match + title similarity (for version differences)
        if ($searchArtistNorm === $trackArtistNorm) {
            // More lenient threshold for electronic/underground artists
            $threshold = $this->isElectronicArtist($searchArtist) ? 0.55 : 0.65;
            $titleSimilarity = $this->fuzzyMatch($searchTitleNorm, $trackTitleNorm, $threshold);
            if ($titleSimilarity) {
                \Log::info("🎵 Match found: Exact artist + title similarity ($threshold)");
                return true;
            }
        }

        // Strategy 3: Check for collaborative artist matches (A & B should match A or B)
        $artistMatch = $this->checkArtistCollaborationMatch($searchArtistNorm, $trackArtistNorm);
        
        if ($artistMatch) {
            // If artists match (including collaboration cases), require moderate title similarity
            $titleSimilarity = $this->fuzzyMatch($searchTitleNorm, $trackTitleNorm, 0.65);
            if ($titleSimilarity) {
                \Log::info("🎵 Match found: Artist collaboration match + title similarity");
                return true;
            }
        }

        // Strategy 4: Contains match (either direction) - for partial matches
        $artistContains = strpos($searchArtistNorm, $trackArtistNorm) !== false || 
                         strpos($trackArtistNorm, $searchArtistNorm) !== false;
        $titleContains = strpos($searchTitleNorm, $trackTitleNorm) !== false || 
                        strpos($trackTitleNorm, $searchTitleNorm) !== false;
        
        if ($artistContains && $titleContains) {
            \Log::info("🎵 Match found: Contains match");
            return true;
        }

        // Strategy 5: Balanced fuzzy match 
        $artistFuzzy = $this->fuzzyMatch($searchArtistNorm, $trackArtistNorm, 0.7);
        $titleFuzzy = $this->fuzzyMatch($searchTitleNorm, $trackTitleNorm, 0.65);

        if ($artistFuzzy && $titleFuzzy) {
            \Log::info("🎵 Match found: Fuzzy match (0.7/0.65 threshold)");
            return true;
        }

        \Log::info("🎵 No match found for track", [
            'reason' => 'All matching strategies failed - similarity too low'
        ]);

        return false;
    }

    /**
     * Check if artists match including collaboration scenarios
     */
    private function checkArtistCollaborationMatch(string $searchArtistNorm, string $trackArtistNorm): bool
    {
        // Exact match
        if ($searchArtistNorm === $trackArtistNorm) {
            return true;
        }

        // Split by common collaboration separators
        $searchArtists = preg_split('/\s*[&,]\s*|\s+feat\s+|\s+ft\s+/', $searchArtistNorm);
        $trackArtists = preg_split('/\s*[&,]\s*|\s+feat\s+|\s+ft\s+/', $trackArtistNorm);

        // Check if any search artist matches any track artist
        foreach ($searchArtists as $searchArt) {
            $searchArt = trim($searchArt);
            if (empty($searchArt)) continue;
            
            foreach ($trackArtists as $trackArt) {
                $trackArt = trim($trackArt);
                if (empty($trackArt)) continue;
                
                // Exact match or high similarity for individual artists
                if ($searchArt === $trackArt || $this->fuzzyMatch($searchArt, $trackArt, 0.9)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an artist is likely from electronic/underground genre
     */
    private function isElectronicArtist(string $artistName): bool
    {
        $artistLower = strtolower($artistName);
        
        // Known electronic/techno/house artists that might have track variations
        $electronicArtists = [
            'paul kalkbrenner', 'k-lone', 'bicep', 'four tet', 'caribou',
            'bonobo', 'moderat', 'apparat', 'kiara scuro', 'rodhad',
            'ben klock', 'marcel dettmann', 'nina kraviz', 'charlotte de witte',
            'amelie lens', 'peggy gou', 'dixon', 'maceo plex', 'tale of us',
            'adriatique', 'stephan bodzin', 'kollektiv turmstrasse', 'mind against',
            'recondite', 'max richter', 'nils frahm', 'jon hopkins', 'clark',
            'burial', 'flying lotus', 'aphex twin', 'boards of canada'
        ];
        
        foreach ($electronicArtists as $electronicArtist) {
            if (strpos($artistLower, $electronicArtist) !== false || 
                strpos($electronicArtist, $artistLower) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Find the best matching Spotify track from search results
     */
    private function findBestSpotifyMatch(array $tracks, string $expectedArtist, string $expectedTitle): ?array
    {
        if (empty($tracks)) {
            return null;
        }

        \Log::info("🔍 Finding best Spotify match", [
            'expected_artist' => $expectedArtist,
            'expected_title' => $expectedTitle,
            'search_results_count' => count($tracks)
        ]);

        // Log all search results for debugging
        \Log::info("🔍 ALL Spotify search results:");
        foreach ($tracks as $index => $track) {
            \Log::info("🔍 Result #{$index}", [
                'track_id' => $track['id'] ?? 'unknown',
                'artist' => $track['artists'][0]['name'] ?? 'unknown',
                'title' => $track['name'] ?? 'unknown',
                'album' => $track['album']['name'] ?? 'unknown',
                'popularity' => $track['popularity'] ?? 'unknown'
            ]);
        }

        foreach ($tracks as $index => $track) {
            $trackArtist = $track['artists'][0]['name'] ?? '';
            $trackTitle = $track['name'] ?? '';
            $trackId = $track['id'] ?? '';
            
            \Log::info("🔍 Evaluating track #{$index}", [
                'track_id' => $trackId,
                'track_artist' => $trackArtist,
                'track_title' => $trackTitle
            ]);
            
            // Use our existing track matching logic
            $matchData = [
                'name' => $trackTitle,
                'artists' => [['name' => $trackArtist]]
            ];
            
            if ($this->verifyTrackMatch($expectedArtist, $expectedTitle, $matchData)) {
                \Log::info("🔍 ✅ Track matches!", [
                    'track_id' => $trackId,
                    'match_reason' => 'verifyTrackMatch passed'
                ]);
                
                // Return the first valid match (they're ordered by popularity/relevance)
                return $track;
            } else {
                \Log::info("🔍 ❌ Track doesn't match", [
                    'track_id' => $trackId,
                    'reason' => 'verifyTrackMatch failed'
                ]);
            }
        }
        
        // If no track passes our strict matching, return the first one as fallback
        // (this preserves the original behavior but with logging)
        \Log::warning("🔍 No exact match found, using first result as fallback", [
            'fallback_id' => $tracks[0]['id'] ?? 'unknown',
            'fallback_artist' => $tracks[0]['artists'][0]['name'] ?? 'unknown',
            'fallback_title' => $tracks[0]['name'] ?? 'unknown'
        ]);
        
        return $tracks[0];
    }

    /**
     * Filter out tracks from the same artist as the seed track
     */
    private function filterSameArtistTracks(array $tracks, string $seedArtist): array
    {
        $seedArtistNorm = $this->normalizeForMatching($seedArtist);
        
        return array_filter($tracks, function($track) use ($seedArtistNorm) {
            $trackArtist = $track['artist']['name'] ?? $track['artist'] ?? $track['subtitle'] ?? '';
            $trackArtistNorm = $this->normalizeForMatching($trackArtist);
            
            // Don't include tracks from the same artist
            return !$this->fuzzyMatch($seedArtistNorm, $trackArtistNorm, 0.9);
        });
    }


    /**
     * Get track preview/oEmbed data
     * Works with Shazam, Last.fm, and Spotify tracks
     * GET /api/music-discovery/track-preview
     */
    public function getTrackPreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'artist_name' => 'required|string',
            'track_title' => 'required|string',
            'original_artist' => 'sometimes|string', // Original unmodified artist name for fallback
            'original_title' => 'sometimes|string', // Original unmodified title for fallback
            'source' => 'required|string|in:shazam,spotify,lastfm',
            'track_id' => 'sometimes|string', // Spotify track ID if source is spotify
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $artistName = trim($request->input('artist_name'));
        $trackTitle = trim($request->input('track_title'));
        $originalArtist = trim($request->input('original_artist', $artistName));
        $originalTitle = trim($request->input('original_title', $trackTitle));
        $source = $request->input('source');
        $trackId = $request->input('track_id');

        try {

            $spotifyTrackId = null;

            if ($source === 'spotify' && $trackId) {
                // For Spotify tracks, use the provided track ID directly
                $spotifyTrackId = $trackId;
                \Log::info("🎵 Preview: Using provided Spotify track ID", [
                    'track_id' => $spotifyTrackId,
                    'artist' => $artistName,
                    'title' => $trackTitle
                ]);
            } else {
                // For Shazam/Last.fm tracks, use RapidAPI search-based matching
                $spotifyTrackId = $this->getSpotifyTrackIdViaSearch($artistName, $trackTitle, $originalArtist, $originalTitle);

                if (!$spotifyTrackId) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Could not find track on Spotify for preview'
                    ], 404);
                }
            }

            // Get Spotify oEmbed data
            $oEmbedData = $this->getSpotifyOEmbedData($spotifyTrackId);

            if (!$oEmbedData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Could not generate preview for this track'
                ], 404);
            }

            // Try to get track metadata using RapidAPI
            $trackMetadata = null;
            try {
                if ($this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                    \Log::info("🎵 Preview: Fetching track metadata via RapidAPI", [
                        'track_id' => $spotifyTrackId
                    ]);

                    $trackResult = $this->rapidApiSpotifyService->getTrackById($spotifyTrackId);

                    if ($trackResult['success'] && isset($trackResult['data'])) {
                        $trackData = $trackResult['data'];

                        $trackMetadata = [
                            'popularity' => $trackData['popularity'] ?? null,
                            'release_date' => $trackData['album']['release_date'] ?? null,
                            'preview_url' => $trackData['preview_url'] ?? null
                        ];

                        // Get artist followers if artist ID is available
                        if (isset($trackData['artists'][0]['id'])) {
                            $artistId = $trackData['artists'][0]['id'];
                            try {
                                $artistResult = $this->rapidApiSpotifyService->getArtistById($artistId);
                                if ($artistResult['success'] && isset($artistResult['data']['followers']['total'])) {
                                    $trackMetadata['followers'] = $artistResult['data']['followers']['total'];
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Failed to get artist data via RapidAPI: ' . $e->getMessage());
                            }
                        }

                        // Get album label using official Spotify API (as per requirements)
                        if (isset($trackData['album']['id'])) {
                            $albumId = $trackData['album']['id'];
                            try {
                                $albumData = $this->spotifyService->getAlbum($albumId);
                                if ($albumData && isset($albumData['label'])) {
                                    $trackMetadata['label'] = $albumData['label'];
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Failed to get album label: ' . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Failed to get track metadata, continue without it
                \Log::warning('Failed to get track metadata for preview: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'spotify_track_id' => $spotifyTrackId,
                    'oembed' => $oEmbedData,
                    'source' => $source,
                    'artist' => $artistName,
                    'title' => $trackTitle,
                    'metadata' => $trackMetadata
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Track preview failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate track preview'
            ], 500);
        }
    }

    /**
     * Get Spotify track ID using RapidAPI search-based matching
     * Uses multiple search strategies with cleaned and original artist/title names
     */
    private function getSpotifyTrackIdViaSearch(string $cleanedArtist, string $cleanedTitle, string $originalArtist, string $originalTitle): ?string
    {
        if (!$this->rapidApiSpotifyService || !RapidApiSpotifyService::enabled()) {
            \Log::warning("🎵 Preview: RapidAPI Spotify service not available");
            return null;
        }

        try {
            // Strategy 1: Try cleaned names first
            \Log::info("🎵 Preview Strategy 1: RapidAPI search with cleaned names", [
                'cleaned_artist' => $cleanedArtist,
                'cleaned_title' => $cleanedTitle
            ]);

            $query = "{$cleanedArtist} {$cleanedTitle}";
            $cleanedTrackTitle = $this->rapidApiSpotifyService->cleanTrackName($cleanedTitle);

            $result = $this->rapidApiSpotifyService->searchTracks($query, 20, 'tracks');

            if ($result['success']) {
                $trackId = $this->rapidApiSpotifyService->findExactMatch($result, $cleanedArtist, $cleanedTrackTitle);
                if ($trackId) {
                    \Log::info("✅ Preview Strategy 1: Success with cleaned names!", [
                        'spotify_track_id' => $trackId
                    ]);
                    return $trackId;
                }
            }

            // Strategy 2: Try original names
            \Log::info("🎵 Preview Strategy 2: RapidAPI search with original names", [
                'original_artist' => $originalArtist,
                'original_title' => $originalTitle
            ]);

            $query = "{$originalArtist} {$originalTitle}";
            $originalTrackTitle = $this->rapidApiSpotifyService->cleanTrackName($originalTitle);

            $result = $this->rapidApiSpotifyService->searchTracks($query, 20, 'tracks');

            if ($result['success']) {
                $trackId = $this->rapidApiSpotifyService->findExactMatch($result, $originalArtist, $originalTrackTitle);
                if ($trackId) {
                    \Log::info("✅ Preview Strategy 2: Success with original names!", [
                        'spotify_track_id' => $trackId
                    ]);
                    return $trackId;
                }
            }

            // Strategy 3: Try with main title only (without feat, remix, etc)
            $mainTitle = preg_replace('/\s*\([^)]*\)/', '', $originalTitle);
            $mainTitle = preg_replace('/\s*\[[^\]]*\]/', '', $mainTitle);
            $mainTitle = trim($mainTitle);

            if ($mainTitle !== $originalTitle) {
                \Log::info("🎵 Preview Strategy 3: RapidAPI search with main title only", [
                    'main_title' => $mainTitle
                ]);

                $query = "{$originalArtist} {$mainTitle}";
                $cleanedMainTitle = $this->rapidApiSpotifyService->cleanTrackName($mainTitle);

                $result = $this->rapidApiSpotifyService->searchTracks($query, 20, 'tracks');

                if ($result['success']) {
                    $trackId = $this->rapidApiSpotifyService->findExactMatch($result, $originalArtist, $cleanedMainTitle);
                    if ($trackId) {
                        \Log::info("✅ Preview Strategy 3: Success with main title!", [
                            'spotify_track_id' => $trackId
                        ]);
                        return $trackId;
                    }
                }
            }

            \Log::warning("🎵 Preview: No matching track found via RapidAPI search");
            return null;

        } catch (\Exception $e) {
            \Log::error('Preview: Failed to get Spotify track ID via search: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean track query for better search results
     */
    private function cleanTrackQuery(string $artistName, string $trackTitle): string
    {
        // Clean artist name - normalize & symbol spacing but keep it
        $cleanArtist = preg_replace('/\s*&\s*/', ' & ', $artistName);
        $cleanArtist = trim($cleanArtist);
        
        // Check if this is a specific remix we want to keep
        $hasSpecificRemix = preg_match('/\([^)]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^)]*\)/i', $trackTitle) ||
                           preg_match('/\[[^\]]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^\]]*\]/i', $trackTitle);
        
        $cleanTitle = $trackTitle;
        
        if ($hasSpecificRemix) {
            // Keep specific remixes (e.g., "Mosca Remix", "David Guetta Remix")
            // Only clean up spacing and formatting
            $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
            $cleanTitle = preg_replace('/\s*-\s*$/', '', $cleanTitle);
            $cleanTitle = preg_replace('/^\s*-\s*/', '', $cleanTitle);
        } else {
            // For non-specific versions, clean more aggressively
            // Remove generic version content
            $cleanTitle = preg_replace('/\s*\([^)]*(?:original|version|radio|extended|edit)(?!\s+remix)[^)]*\)/i', '', $cleanTitle);
            $cleanTitle = preg_replace('/\s*\[[^\]]*(?:original|version|radio|extended|edit)(?!\s+remix)[^\]]*\]/i', '', $cleanTitle);
            
            // Remove standalone generic words at the end
            $cleanTitle = preg_replace('/\s+(?:original|version|radio|extended|edit)$/i', '', $cleanTitle);
            
            // Clean up spacing
            $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
            $cleanTitle = preg_replace('/\s*-\s*$/', '', $cleanTitle);
            $cleanTitle = preg_replace('/^\s*-\s*/', '', $cleanTitle);
        }
        
        $cleanTitle = trim($cleanTitle);
        
        return trim("$cleanArtist $cleanTitle");
    }

    /**
     * Find matching Shazam track from search results
     */
    private function findMatchingShazamTrack(array $searchData, string $artistName, string $trackTitle): ?string
    {
        \Log::info("🎵 findMatchingShazamTrack called", [
            'looking_for_artist' => $artistName,
            'looking_for_title' => $trackTitle,
            'search_data_keys' => array_keys($searchData),
            'has_result' => isset($searchData['result']),
            'result_keys' => isset($searchData['result']) ? array_keys($searchData['result']) : []
        ]);

        // Use same parsing logic as working getShazamRelatedTracks function
        if (!isset($searchData['result']['tracks']['hits'])) {
            \Log::warning("🎵 No 'result.tracks.hits' section in Shazam response", [
                'available_structure' => json_encode($searchData, JSON_PRETTY_PRINT)
            ]);
            return null;
        }

        \Log::info("🎵 Found Shazam hits to process", [
            'total_hits' => count($searchData['result']['tracks']['hits']),
            'first_hit_keys' => !empty($searchData['result']['tracks']['hits']) ? array_keys($searchData['result']['tracks']['hits'][0]) : []
        ]);

        foreach ($searchData['result']['tracks']['hits'] as $hitIndex => $track) {
            \Log::info("🎵 Processing Shazam track #{$hitIndex}", [
                'track_key' => $track['key'] ?? 'no key',
                'track_structure' => array_keys($track),
                'has_heading' => isset($track['heading']),
                'heading_keys' => isset($track['heading']) ? array_keys($track['heading']) : [],
                'found_title' => $track['heading']['title'] ?? 'no title',
                'found_artist' => $track['heading']['subtitle'] ?? 'no artist'
            ]);
            
            $foundTitle = $track['heading']['title'] ?? '';
            $foundArtist = $track['heading']['subtitle'] ?? '';
            $trackId = $track['key'] ?? null;

            if (!$trackId) {
                \Log::warning("🎵 Skipping track #{$hitIndex} - no track ID found");
                continue;
            }

            \Log::info("🎵 About to verify track match for #{$hitIndex}", [
                'track_id' => $trackId,
                'found_title' => $foundTitle,
                'found_artist' => $foundArtist,
                'looking_for_title' => $trackTitle,
                'looking_for_artist' => $artistName
            ]);

            $isMatch = $this->verifyTrackMatch($artistName, $trackTitle, [
                'title' => $foundTitle,
                'subtitle' => $foundArtist
            ]);

            \Log::info("🎵 Track match result for #{$hitIndex}", [
                'track_id' => $trackId,
                'is_match' => $isMatch ? 'YES' : 'NO'
            ]);

            if ($isMatch) {
                \Log::info("🎵 ✅ FOUND MATCHING SHAZAM TRACK!", [
                    'selected_track_id' => $trackId,
                    'matched_title' => $foundTitle,
                    'matched_artist' => $foundArtist,
                    'hit_index' => $hitIndex
                ]);
                return $trackId;
            }
        }

        \Log::warning("🎵 ❌ NO MATCHING SHAZAM TRACK FOUND", [
            'searched_for_artist' => $artistName,
            'searched_for_title' => $trackTitle,
            'total_hits_checked' => count($searchData['result']['tracks']['hits']),
            'reason' => 'None of the search results passed verifyTrackMatch()',
            'suggestion' => 'Check if track name/artist differs from what we searched for'
        ]);
        return null;
    }

    /**
     * Search Spotify directly by artist and title (fallback when ISRC is not available)
     */
    private function searchSpotifyByArtistAndTitle(string $artistName, string $trackTitle): ?string
    {
        if (!$this->rapidApiSpotifyService || !RapidApiSpotifyService::enabled()) {
            \Log::warning("🎵 RapidAPI Spotify service not available for search");
            return null;
        }

        try {
            \Log::info("🎵 Searching via RapidAPI", [
                'artist' => $artistName,
                'title' => $trackTitle
            ]);

            $query = "{$artistName} {$trackTitle}";
            $cleanedTitle = $this->rapidApiSpotifyService->cleanTrackName($trackTitle);

            $result = $this->rapidApiSpotifyService->searchTracks($query, 10, 'tracks');

            if ($result['success']) {
                $trackId = $this->rapidApiSpotifyService->findExactMatch($result, $artistName, $cleanedTitle);
                if ($trackId) {
                    \Log::info("✅ Found exact match via RapidAPI", [
                        'spotify_track_id' => $trackId
                    ]);
                    return $trackId;
                }
            }

            \Log::warning("🎵 No matching track found via RapidAPI");
            return null;

        } catch (\Exception $e) {
            \Log::error("🎵 Preview Fallback: Spotify search error", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract ISRC from Shazam track details
     */
    private function extractIsrcFromShazamDetails(array $detailsData): ?string
    {
        // ISRC can be in different locations in Shazam's response
        $isrc = $detailsData['isrc'] ?? 
                $detailsData['track']['isrc'] ?? 
                $detailsData['data']['isrc'] ?? 
                null;

        if ($isrc) {
            \Log::info("🎵 Preview: Found ISRC", ['isrc' => $isrc]);
            return $isrc;
        }

        // Sometimes ISRC is nested deeper
        if (isset($detailsData['sections'])) {
            foreach ($detailsData['sections'] as $section) {
                if (isset($section['metadata'])) {
                    foreach ($section['metadata'] as $metadata) {
                        if (isset($metadata['title']) && 
                            strtolower($metadata['title']) === 'isrc' && 
                            isset($metadata['text'])) {
                            $isrc = $metadata['text'];
                            \Log::info("🎵 Preview: Found ISRC in metadata", ['isrc' => $isrc]);
                            return $isrc;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Search Spotify by ISRC
     */
    private function searchSpotifyByIsrc(string $isrc): ?string
    {
        try {
            $accessToken = $this->getSpotifyAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->get("https://api.spotify.com/v1/search", [
                'q' => "isrc:$isrc",
                'type' => 'track',
                'limit' => 1
            ]);

            if (!$response->successful()) {
                \Log::warning("🎵 Preview: Spotify ISRC search failed", [
                    'status' => $response->status(),
                    'isrc' => $isrc
                ]);
                return null;
            }

            $data = $response->json();
            
            if (isset($data['tracks']['items'][0]['id'])) {
                $spotifyTrackId = $data['tracks']['items'][0]['id'];
                \Log::info("🎵 Preview: Found Spotify track by ISRC", [
                    'spotify_track_id' => $spotifyTrackId,
                    'isrc' => $isrc
                ]);
                return $spotifyTrackId;
            }

            return null;

        } catch (\Exception $e) {
            \Log::error('Preview: Spotify ISRC search failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Spotify oEmbed data for track preview
     */
    private function getSpotifyOEmbedData(string $spotifyTrackId): ?array
    {
        try {
            $spotifyUrl = "https://open.spotify.com/track/$spotifyTrackId";
            
            $response = Http::get("https://open.spotify.com/oembed", [
                'url' => $spotifyUrl,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $oEmbedData = $response->json();
                \Log::info("🎵 Preview: Got Spotify oEmbed data", [
                    'spotify_track_id' => $spotifyTrackId
                ]);
                return $oEmbedData;
            }

            \Log::warning("🎵 Preview: Spotify oEmbed failed", [
                'status' => $response->status(),
                'spotify_track_id' => $spotifyTrackId
            ]);
            return null;

        } catch (\Exception $e) {
            \Log::error('Preview: Spotify oEmbed failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Try to get Shazam recommendations from artist's most popular track as fallback
     */
    private function tryShazamArtistFallback(string $artistName, string $originalTitle, int $limit): array
    {
        try {
            \Log::info("🎵 🔍 FALLBACK: Searching for artist's tracks", [
                'artist' => $artistName,
                'original_title' => $originalTitle
            ]);

            // Search for artist only to get their track list
            $response = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
            ])->get("https://shazam-api6.p.rapidapi.com/shazam/search_track/", [
                'query' => $artistName,
                'limit' => 15
            ]);

            if (!$response->successful()) {
                \Log::warning("🎵 ❌ FALLBACK: Artist search failed", [
                    'status' => $response->status(),
                    'artist' => $artistName
                ]);
                return [];
            }

            $searchData = $response->json();
            $tracks = $searchData['result']['tracks']['hits'] ?? [];

            if (empty($tracks)) {
                \Log::warning("🎵 ❌ FALLBACK: No tracks found for artist", [
                    'artist' => $artistName
                ]);
                return [];
            }

            \Log::info("🎵 ✅ FALLBACK: Found artist tracks", [
                'artist' => $artistName,
                'tracks_found' => count($tracks),
                'first_track_title' => $tracks[0]['heading']['title'] ?? 'Unknown'
            ]);

            // Get the first track (most popular/relevant) that's NOT the original track
            $fallbackTrackId = null;
            $fallbackTrackTitle = null;
            
            foreach ($tracks as $track) {
                $trackTitle = $track['heading']['title'] ?? '';
                $trackArtist = $track['heading']['subtitle'] ?? '';
                
                // Skip if it's the same track we already tried
                if ($this->normalizeForMatching($trackTitle) === $this->normalizeForMatching($originalTitle)) {
                    \Log::info("🎵 ⏭️ FALLBACK: Skipping original track", [
                        'skipped_title' => $trackTitle
                    ]);
                    continue;
                }
                
                $fallbackTrackId = $track['key'] ?? null;
                $fallbackTrackTitle = $trackTitle;
                break;
            }

            if (!$fallbackTrackId) {
                \Log::warning("🎵 ❌ FALLBACK: No alternative tracks found", [
                    'artist' => $artistName,
                    'reason' => 'All tracks were the same as original'
                ]);
                return [];
            }

            \Log::info("🎵 🎯 FALLBACK: Trying recommendations from alternative track", [
                'fallback_track_id' => $fallbackTrackId,
                'fallback_title' => $fallbackTrackTitle,
                'original_title' => $originalTitle
            ]);

            // Get recommendations from the fallback track
            $relatedResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api6.p.rapidapi.com'
            ])->get("https://shazam-api6.p.rapidapi.com/shazam/similar_tracks", [
                'track_id' => $fallbackTrackId,
                'limit' => min($limit, 50)
            ]);

            if (!$relatedResponse->successful()) {
                \Log::warning("🎵 ❌ FALLBACK: Recommendations failed", [
                    'status' => $relatedResponse->status(),
                    'fallback_track_id' => $fallbackTrackId
                ]);
                return [];
            }

            $relatedData = $relatedResponse->json();
            $fallbackTracks = [];

            if (isset($relatedData['result']['tracks']) && is_array($relatedData['result']['tracks'])) {
                foreach ($relatedData['result']['tracks'] as $track) {
                    $fallbackTracks[] = [
                        'id' => $track['key'] ?? null,
                        'key' => $track['key'] ?? null,
                        'title' => $track['title'] ?? 'Unknown Title',
                        'duration' => 180,
                        'artist' => [
                            'name' => $track['subtitle'] ?? 'Unknown Artist'
                        ],
                        'subtitle' => $track['subtitle'] ?? 'Unknown Artist',
                        'album' => [
                            'title' => 'Unknown Album',
                            'release_date' => null
                        ],
                        'source' => 'shazam_fallback'
                    ];
                }
            }

            \Log::info("🎵 ✅ FALLBACK: Complete", [
                'fallback_track_id' => $fallbackTrackId,
                'fallback_title' => $fallbackTrackTitle,
                'recommendations_found' => count($fallbackTracks),
                'success' => count($fallbackTracks) > 0
            ]);

            return $fallbackTracks;

        } catch (\Exception $e) {
            \Log::error("🎵 ❌ FALLBACK: Exception occurred", [
                'error' => $e->getMessage(),
                'artist' => $artistName
            ]);
            return [];
        }
    }

}