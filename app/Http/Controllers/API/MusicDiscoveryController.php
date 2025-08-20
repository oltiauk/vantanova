<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SpotifyService;
use App\Services\SoundStatsService;
use App\Services\RapidApiService;
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
        private ?RapidApiService $rapidApiService = null
    ) {}


    /**
     * Get related tracks using Spotify and Shazam APIs
     * GET /api/music-discovery/related-tracks
     */
    public function getRelatedTracks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'track_id' => 'required|string',
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

        $trackId = $request->input('track_id');
        $artistName = $request->input('artist_name');
        $trackTitle = $request->input('track_title');
        $limit = $request->input('limit', 50);

        try {
            $allTracks = [];
            $spotifyTracks = [];
            $shazamTracks = [];

            // 1. Search for the track on Spotify to get Track ID
            $spotifySearchResults = $this->searchSpotifyForTrack($artistName, $trackTitle);
            \Log::info("🔍 Spotify search results", [
                'artist' => $artistName,
                'title' => $trackTitle,
                'found_results' => !empty($spotifySearchResults),
                'has_tracks' => isset($spotifySearchResults['tracks']),
                'track_count' => isset($spotifySearchResults['tracks']['items']) ? count($spotifySearchResults['tracks']['items']) : 0
            ]);
            $spotifyTrackId = null;

            if ($spotifySearchResults && isset($spotifySearchResults['tracks']['items'][0])) {
                $spotifyTrack = $spotifySearchResults['tracks']['items'][0];
                $spotifyTrackId = $spotifyTrack['id'];

                // Always get Spotify recommendations - no strict matching
                $spotifyTracks = $this->getSpotifyRelatedTracks($spotifyTrackId, $limit);
                \Log::info("🎧 Got Spotify tracks", ['count' => count($spotifyTracks)]);
            }

            // 3. Search on Shazam and get related tracks
            $shazamTracks = $this->getShazamRelatedTracks($artistName, $trackTitle, $limit);

            \Log::info("🎧 Got Shazam tracks", ['count' => count($shazamTracks)]);

            // 4. Combine all tracks
            $allTracks = array_merge($spotifyTracks, $shazamTracks);

            // 5. Remove duplicates only
            $allTracks = $this->removeDuplicateTracks($allTracks);

            // 6. Format tracks to match frontend expectations BEFORE filtering
            $formattedTracks = $this->formatRelatedTracksArray($allTracks);

            // 7. Apply user preference filtering (blacklist filtering) on formatted tracks
            $userId = auth()->id();
            if ($userId) {
                $beforeCount = count($formattedTracks);
                $formattedTracks = $this->filterByUserPreferences($formattedTracks, $userId);
                $afterCount = count($formattedTracks);
                \Log::info("🎧 Applied user preference filtering", ['before' => $beforeCount, 'after' => $afterCount]);
            }

            // 8. Randomize order
            shuffle($formattedTracks);

            // 9. Limit results
            $formattedTracks = array_slice($formattedTracks, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => $formattedTracks,
                'total' => count($formattedTracks),
                'spotify_count' => count($spotifyTracks),
                'shazam_count' => count($shazamTracks),
                'after_deduplication' => count($formattedTracks),
                'requested' => $limit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get related tracks: ' . $e->getMessage()
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
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('query');
        $limit = $request->input('limit', 20);

        // Try RapidAPI first, fallback to Deezer if unavailable  
        if ($this->rapidApiService && RapidApiService::enabled()) {
            try {
                $results = $this->rapidApiService->searchTracks($query, $limit);

                if ($results['success']) {
                    return response()->json([
                        'success' => true,
                        'data' => $this->formatRapidApiTracksArray($results['tracks'] ?? [])
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('RapidAPI search failed, falling back to Deezer: ' . $e->getMessage());
                // Check if it's a subscription error
                if (strpos($e->getMessage(), 'not subscribed') !== false || strpos($e->getMessage(), '403') !== false) {
                    \Log::warning('RapidAPI subscription issue detected, using Deezer fallback');
                }
            }
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
                $filteredTracks = $this->filterByUserPreferences($filteredTracks, $userId);
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
     * Filter tracks by user preferences (blacklist/saved)
     */
    private function filterByUserPreferences(array $tracks, int $userId): array
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

            return array_filter($tracks, function($track) use ($blacklistedIsrcs, $savedIsrcs, $blacklistedArtistIds, $blacklistedArtistNames) {
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
                'artists' => array_map(fn($artist) => $artist['name'], $track['artists'] ?? []),
                'album' => $track['album']['name'] ?? 'Unknown Album',
                'album_image' => $track['album']['images'][0]['url'] ?? null,
                'image' => $track['album']['images'][0]['url'] ?? null, // Add this for compatibility
                'duration_ms' => $track['duration_ms'] ?? 0,
                'duration' => $this->formatDuration($track['duration_ms'] ?? 0),
                'preview_url' => $track['preview_url'],
                'external_url' => $track['external_urls']['spotify'] ?? null,
                'popularity' => $track['popularity'] ?? 0,
                'release_date' => $track['album']['release_date'] ?? null,
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
            
            return [
                'id' => $track['id'] ?? '',
                'name' => $track['title'] ?? '', // Convert 'title' to 'name'
                'artist' => is_array($track['artist']) ? ($track['artist']['name'] ?? 'Unknown Artist') : ($track['artist'] ?? 'Unknown Artist'),
                'album' => is_array($track['album']) ? ($track['album']['title'] ?? 'Unknown Album') : ($track['album'] ?? 'Unknown Album'),
                'duration_ms' => isset($track['duration']) ? $track['duration'] * 1000 : 0,
                'external_url' => $track['external_url'] ?? null,
                'preview_url' => $track['preview_url'] ?? null,
                'image' => $extractedImage,
                'uri' => isset($track['id']) ? "spotify:track:{$track['id']}" : null,
                'external_ids' => $track['external_ids'] ?? [], // Add external_ids for ISRC
                'artists' => isset($track['artist']['name']) ? [['id' => '', 'name' => $track['artist']['name']]] : [] // Add artists array
            ];
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
            'image' => $track['album']['images'][0]['url'] ?? null,
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
                'X-RapidAPI-Host' => 'shazam-api7.p.rapidapi.com'
            ])
            ->get("https://shazam-api7.p.rapidapi.com/search", [
                'q' => "{$artistName} {$trackTitle}"
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
                $tracks = $shazamData['tracks']['hits'] ?? [];
                
                if (!empty($tracks)) {
                    $shazamTrackId = $tracks[0]['track']['key'] ?? null;
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
                    'X-RapidAPI-Host' => 'shazam-api7.p.rapidapi.com'
                ])
                ->get("https://shazam-api7.p.rapidapi.com/songs/list-recommendations", [
                    'id' => $trackId,
                    'limit' => '100'
                ]);

            if (!$response->successful()) {
                \Log::warning("Shazam recommendations failed: HTTP {$response->status()}");
                return [];
            }

            $data = $response->json();
            $tracks = $data['tracks'] ?? [];
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
                        ],
                        'duration' => intval($track['duration_ms'] / 1000),
                        'preview_url' => $track['preview_url'],
                        'external_urls' => $track['external_urls'],
                        'spotify_id' => $track['id'],
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
            $title = $track['title'] ?? '';
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
        
        // Remove common punctuation but keep some structure for better matching
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $normalized); // Keep hyphens
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Collapse multiple spaces
        $normalized = trim($normalized);
        
        return $normalized;
    }

    /**
     * Fuzzy match two normalized strings
     */
    private function fuzzyMatch(string $str1, string $str2, float $threshold = 0.8): bool
    {
        if ($str1 === $str2) {
            return true;
        }
        
        // Check if one string contains the other
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return true;
        }
        
        // Simple similarity check
        $similarity = 0;
        similar_text($str1, $str2, $similarity);
        
        return ($similarity / 100) >= $threshold;
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
     * Search for a track on Spotify using artist and title
     */
    private function searchSpotifyForTrack(string $artistName, string $trackTitle): ?array
    {
        try {
            $accessToken = $this->getSpotifyAccessToken();
            $query = urlencode("$artistName $trackTitle");
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->get("https://api.spotify.com/v1/search", [
                'q' => $query,
                'type' => 'track',
                'limit' => 5
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            \Log::warning("Spotify search failed: " . $e->getMessage());
        }
        
        return null;
    }


    /**
     * Get related tracks from Spotify using the RapidAPI Spotify endpoint
     */
    private function getSpotifyRelatedTracks(string $spotifyTrackId, int $limit): array
    {
        try {
            // Step 1: Get playlist from track using RapidAPI
            \Log::info("🎧 Calling RapidAPI seed_to_playlist", [
                'spotify_track_id' => $spotifyTrackId,
                'uri' => "spotify:track:$spotifyTrackId"
            ]);
            
            $playlistResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
            ])->get("https://spotify81.p.rapidapi.com/seed_to_playlist", [
                'uri' => "spotify:track:$spotifyTrackId"
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
            $tracksResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'spotify81.p.rapidapi.com'
            ])->get("https://spotify81.p.rapidapi.com/playlist_tracks", [
                'id' => $playlistId,
                'offset' => 0,
                'limit' => min($limit, 100)
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
                            'source' => 'spotify'
                        ];
                    }
                }
            }

            return $tracks;

        } catch (\Exception $e) {
            \Log::warning("Spotify related tracks failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get related tracks from Shazam using RapidAPI
     */
    private function getShazamRelatedTracks(string $artistName, string $trackTitle, int $limit): array
    {
        try {
            // Step 1: Search for the track on Shazam to get its ID
            \Log::info("🎵 Calling Shazam search (artist name only)", [
                'artist' => $artistName,
                'title' => $trackTitle,
                'search_term' => $artistName,
                'strategy' => 'Search by artist name only, then find track by title in results'
            ]);
            
            $searchResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api7.p.rapidapi.com'
            ])->get("https://shazam-api7.p.rapidapi.com/search", [
                'term' => $artistName, // Search by artist name only
                'limit' => 10
            ]);

            \Log::info("🎵 Shazam search response", [
                'status' => $searchResponse->status(),
                'successful' => $searchResponse->successful(),
                'body_preview' => substr($searchResponse->body(), 0, 500) . '...',
                'full_body' => $searchResponse->body()
            ]);

            if (!$searchResponse->successful()) {
                \Log::warning("Shazam search failed: " . $searchResponse->body());
                return [];
            }

            $searchData = $searchResponse->json();
            $shazamTrackId = null;

            \Log::info("🎵 Shazam search data structure", [
                'has_tracks' => isset($searchData['tracks']),
                'has_data' => isset($searchData['data']),
                'keys' => array_keys($searchData ?? []),
                'data_keys' => isset($searchData['data']) ? array_keys($searchData['data']) : []
            ]);

            // Try to find the track ID from search results - search by artist, then find track by title
            \Log::info("🎵 Processing Shazam search results", [
                'total_sections' => isset($searchData['data']) ? count($searchData['data']) : 0,
                'looking_for_title' => $trackTitle,
                'looking_for_artist' => $artistName
            ]);
            
            if (isset($searchData['data'])) {
                foreach ($searchData['data'] as $sectionName => $content) {
                    \Log::info("🎵 Processing section: $sectionName", [
                        'has_hits' => isset($content['hits']),
                        'hits_count' => isset($content['hits']) ? count($content['hits']) : 0
                    ]);
                    
                    if (is_array($content) && isset($content['hits'])) {
                        foreach ($content['hits'] as $hitIndex => $hit) {
                            $track = isset($hit['track']) ? $hit['track'] : $hit;
                            $foundTitle = $track['title'] ?? '';
                            $foundArtist = $track['subtitle'] ?? '';
                            $trackId = $track['key'] ?? $track['id'] ?? null;
                            
                            \Log::info("🎵 Examining track #{$hitIndex}", [
                                'found_title' => $foundTitle,
                                'found_artist' => $foundArtist,
                                'track_id' => $trackId,
                                'full_track_data' => $track
                            ]);
                            
                            // Check if this track matches our search title
                            if ($trackId && $this->verifyTrackMatch($artistName, $trackTitle, $track)) {
                                $shazamTrackId = $trackId;
                                \Log::info("🎵 ✅ MATCH FOUND - Using this Shazam track", [
                                    'track_id' => $shazamTrackId,
                                    'found_title' => $foundTitle,
                                    'found_artist' => $foundArtist,
                                    'searched_title' => $trackTitle,
                                    'searched_artist' => $artistName,
                                    'section' => $sectionName,
                                    'hit_index' => $hitIndex
                                ]);
                                break 2;
                            } else {
                                \Log::info("🎵 ❌ No match - continuing search", [
                                    'reason' => $trackId ? 'verifyTrackMatch failed' : 'no track ID found'
                                ]);
                            }
                        }
                    }
                }
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
            
            $relatedResponse = Http::withHeaders([
                'X-RapidAPI-Key' => '79b6dcd257mshbc9507f57cf0eaep167467jsnb8e071cc7311',
                'X-RapidAPI-Host' => 'shazam-api7.p.rapidapi.com'
            ])->get("https://shazam-api7.p.rapidapi.com/songs/list-recommendations", [
                'id' => $shazamTrackId,
                'limit' => min($limit, 50) // Limit to maximum 50 tracks
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
            $tracks = [];

            \Log::info("🎵 Shazam recommendations response", [
                'status' => $relatedResponse->status(),
                'has_tracks' => isset($relatedData['tracks']),
                'tracks_count' => isset($relatedData['tracks']) ? count($relatedData['tracks']) : 0,
                'response_keys' => array_keys($relatedData ?? []),
                'full_response' => $relatedData
            ]);

            if (isset($relatedData['tracks']) && is_array($relatedData['tracks'])) {
                foreach ($relatedData['tracks'] as $index => $track) {
                    \Log::info("🎵 Processing Shazam recommendation #{$index}", [
                        'track_key' => $track['key'] ?? 'no key',
                        'title' => $track['title'] ?? 'no title',
                        'subtitle' => $track['subtitle'] ?? 'no subtitle',
                        'full_track' => $track
                    ]);
                    
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

            \Log::info("🎵 ✅ SHAZAM RECOMMENDATIONS COMPLETE", [
                'seed_track_id' => $shazamTrackId,
                'total_tracks_collected' => count($tracks),
                'limit_requested' => $limit,
                'limit_applied' => min($limit, 50),
                'tracks_under_limit' => count($tracks) <= 50
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
        $trackTitle = $track['name'] ?? $track['title'] ?? '';

        // Extract artist name from different formats
        if (isset($track['artists'][0]['name'])) {
            $trackArtist = $track['artists'][0]['name'];
        } elseif (isset($track['subtitle'])) {
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

        // Try multiple matching strategies
        // Strategy 1: Exact match after normalization
        if ($searchArtistNorm === $trackArtistNorm && $searchTitleNorm === $trackTitleNorm) {
            \Log::info("🎵 Match found: Exact match");
            return true;
        }

        // Strategy 2: Contains match (either direction)
        $artistContains = strpos($searchArtistNorm, $trackArtistNorm) !== false || 
                         strpos($trackArtistNorm, $searchArtistNorm) !== false;
        $titleContains = strpos($searchTitleNorm, $trackTitleNorm) !== false || 
                        strpos($trackTitleNorm, $searchTitleNorm) !== false;
        
        if ($artistContains && $titleContains) {
            \Log::info("🎵 Match found: Contains match");
            return true;
        }

        // Strategy 3: Fuzzy match with lower threshold (more lenient)
        $artistMatch = $this->fuzzyMatch($searchArtistNorm, $trackArtistNorm, 0.6);
        $titleMatch = $this->fuzzyMatch($searchTitleNorm, $trackTitleNorm, 0.6);

        if ($artistMatch && $titleMatch) {
            \Log::info("🎵 Match found: Fuzzy match (0.6 threshold)");
            return true;
        }

        // Strategy 4: Word-based matching (split by spaces and check if key words match)
        $searchArtistWords = explode(' ', $searchArtistNorm);
        $trackArtistWords = explode(' ', $trackArtistNorm);
        $searchTitleWords = explode(' ', $searchTitleNorm);
        $trackTitleWords = explode(' ', $trackTitleNorm);

        // Check if main words are present
        $artistWordMatch = false;
        foreach ($searchArtistWords as $word) {
            if (strlen($word) > 2) { // Skip short words
                foreach ($trackArtistWords as $trackWord) {
                    if (strpos($trackWord, $word) !== false || strpos($word, $trackWord) !== false) {
                        $artistWordMatch = true;
                        break 2;
                    }
                }
            }
        }

        $titleWordMatch = false;
        foreach ($searchTitleWords as $word) {
            if (strlen($word) > 2) { // Skip short words
                foreach ($trackTitleWords as $trackWord) {
                    if (strpos($trackWord, $word) !== false || strpos($word, $trackWord) !== false) {
                        $titleWordMatch = true;
                        break 2;
                    }
                }
            }
        }

        if ($artistWordMatch && $titleWordMatch) {
            \Log::info("🎵 Match found: Word-based match");
            return true;
        }

        \Log::info("🎵 No match found for track", [
            'reason' => 'All matching strategies failed'
        ]);

        return false;
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
}