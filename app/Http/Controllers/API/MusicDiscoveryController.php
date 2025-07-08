<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SpotifyService;
use App\Services\SoundStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class MusicDiscoveryController extends Controller
{
    public function __construct(
        private SpotifyService $spotifyService,
        private SoundStatsService $soundStatsService
    ) {}

    /**
     * Search for seed tracks on Spotify
     * POST /api/music-discovery/search-seed
     */
    public function searchSeedTracks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('query');
        $limit = $request->input('limit', 20);

        $results = $this->spotifyService->searchTracks($query, $limit);

        return response()->json([
            'success' => true,
            'data' => $this->formatSpotifyTracksArray($results['tracks']['items'] ?? [])
        ]);
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
        $limit = $request->input('limit', 20);

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
     * Get track audio features (for displaying BPM, key, etc.)
     * GET /api/music-discovery/track-features/{trackId}
     */
    public function getTrackFeatures(string $trackId): JsonResponse
    {
        $features = $this->spotifyService->getTrackAudioFeatures($trackId);

        if (!$features) {
            return response()->json([
                'success' => false,
                'message' => 'Track features not found'
            ], 404);
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
     * Format duration from milliseconds to MM:SS
     */
    private function formatDuration(int $durationMs): string
    {
        $seconds = round($durationMs / 1000);
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}