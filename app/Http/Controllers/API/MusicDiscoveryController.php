<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SpotifyService;
use App\Services\SoundStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'data' => $this->formatSpotifyTracks($results['tracks']['items'] ?? [])
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
                'data' => [
                    'seed_track' => [
                        'id' => $seedTrackId,
                        'name' => $request->input('seed_track_name'),
                        'artist' => $request->input('seed_track_artist'),
                    ],
                    'parameters' => $parameters,
                    'recommendations' => [],
                    'total' => 0
                ]
            ]);
        }

        // Get full track details from Spotify
        $spotifyData = $this->spotifyService->batchGetTracks($trackIds);
        $tracks = $spotifyData['tracks'] ?? [];

        // Filter out null tracks (invalid IDs)
        $validTracks = array_filter($tracks, fn($track) => $track !== null);

        return response()->json([
            'success' => true,
            'data' => [
                'seed_track' => [
                    'id' => $seedTrackId,
                    'name' => $request->input('seed_track_name'),
                    'artist' => $request->input('seed_track_artist'),
                ],
                'parameters' => $parameters,
                'recommendations' => $this->formatSpotifyTracks($validTracks),
                'total' => count($validTracks)
            ]
        ]);
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
     * Format Spotify track data for frontend consumption
     */
    private function formatSpotifyTracks(array $tracks): array
    {
        return array_map(function ($track) {
            return [
                'id' => $track['id'],
                'name' => $track['name'],
                'artist' => $track['artists'][0]['name'] ?? 'Unknown Artist',
                'artists' => array_map(fn($artist) => $artist['name'], $track['artists'] ?? []),
                'album' => $track['album']['name'] ?? 'Unknown Album',
                'album_image' => $track['album']['images'][0]['url'] ?? null,
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