<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedTrack;
use App\Models\SavedTrack;
use App\Models\BlacklistedArtist;
use App\Models\SavedArtist;
use App\Models\SpotifyCache;
use App\Services\SpotifyService;
use App\Services\RapidApiSpotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\ListenedTrack;

class MusicPreferencesController extends Controller
{
    public function __construct(
        private readonly SpotifyService $spotifyService,
        private readonly ?RapidApiSpotifyService $rapidApiSpotifyService = null
    ) {
    }
    /**
     * Check if all required tables exist
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('blacklisted_tracks') && 
               Schema::hasTable('saved_tracks') && 
               Schema::hasTable('blacklisted_artists') && 
               Schema::hasTable('saved_artists') &&
               Schema::hasTable('listened_tracks');
    }

    /**
     * Return error response for missing tables
     */
    private function missingTablesResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Music preferences feature is not yet set up. Please run database migrations or contact your administrator.',
            'code' => 'TABLES_MISSING'
        ], 503);
    }

    /**
     * Blacklist a track by ISRC
     */
    public function blacklistTrack(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        $validator = Validator::make($request->all(), [
            'isrc' => 'required|string',
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'spotify_id' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        
        try {
            BlacklistedTrack::updateOrCreate(
                ['user_id' => $userId, 'isrc' => $request->isrc],
                [
                    'track_name' => $request->track_name,
                    'artist_name' => $request->artist_name,
                    'spotify_id' => $request->spotify_id,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Track blacklisted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to blacklist track'
            ], 500);
        }
    }

    /**
     * Upsert a listened track for the current user
     */
    public function markListened(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        $validator = Validator::make($request->all(), [
            'track_key' => 'required|string',
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'spotify_id' => 'sometimes|string|nullable',
            'isrc' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
            ], 401);
        }

        try {
            $now = now();
            $record = ListenedTrack::updateOrCreate(
                [
                    'user_id' => $userId,
                    'track_key' => $request->track_key,
                ],
                [
                    'track_name' => $request->track_name,
                    'artist_name' => $request->artist_name,
                    'spotify_id' => $request->spotify_id,
                    'isrc' => $request->isrc,
                    'last_listened_at' => $now,
                ]
            );
            if (!$record->first_listened_at) {
                $record->first_listened_at = $now;
                $record->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Track marked as listened',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark track as listened',
            ], 500);
        }
    }

    /**
     * Get listened track keys for the current user
     */
    public function getListenedTracks(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
            ], 401);
        }

        $keys = ListenedTrack::where('user_id', $userId)
            ->orderByDesc('last_listened_at')
            ->pluck('track_key')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $keys,
        ]);
    }

    /**
     * Remove listened mark (optional)
     */
    public function unmarkListened(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'track_key' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        ListenedTrack::where('user_id', $userId)
            ->where('track_key', $request->track_key)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Track unmarked as listened',
        ]);
    }

    /**
     * Save a track by ISRC (24-hour expiration)
     * Fetches track and artist stats via RapidAPI if enabled
     */
    public function saveTrack(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        $validator = Validator::make($request->all(), [
            'isrc' => 'required|string',
            'track_name' => 'required|string',
            'artist_name' => 'required|string',
            'spotify_id' => 'sometimes|string|nullable',
            'label' => 'sometimes|string|nullable',
            'popularity' => 'sometimes|integer|nullable|min:0|max:100',
            'followers' => 'sometimes|integer|nullable|min:0',
            'release_date' => 'sometimes|string|nullable',
            'preview_url' => 'sometimes|string|nullable',
            'track_count' => 'sometimes|integer|nullable|min:1',
            'is_single_track' => 'sometimes|boolean|nullable',
            'album_id' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $spotifyId = $request->spotify_id;
            $popularity = $request->popularity;
            $followers = $request->followers;

            // Try to fetch track stats via RapidAPI if enabled and spotify_id not provided
            if ($this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                \Log::info('💾 Save Track: Using RapidAPI to fetch stats', [
                    'track_name' => $request->track_name,
                    'artist_name' => $request->artist_name,
                    'spotify_id_provided' => !empty($spotifyId)
                ]);

                // Step 1: If no spotify_id, search for it
                if (!$spotifyId) {
                    $query = "{$request->artist_name} {$request->track_name}";
                    $searchResult = $this->rapidApiSpotifyService->searchTracks($query, 10, 'tracks');

                    if ($searchResult['success']) {
                        $spotifyId = $this->rapidApiSpotifyService->findExactMatch(
                            $searchResult,
                            $request->artist_name,
                            $request->track_name
                        );

                        if ($spotifyId) {
                            \Log::info('💾 Save Track: Found Spotify ID via search', [
                                'spotify_id' => $spotifyId
                            ]);
                        }
                    }
                }

                // Step 2: If we have spotify_id, fetch track details for popularity and artist_id
                if ($spotifyId) {
                    $trackResult = $this->rapidApiSpotifyService->getTrackById($spotifyId);

                    if ($trackResult['success'] && isset($trackResult['data'])) {
                        $trackData = $trackResult['data'];

                        // Extract popularity
                        $popularity = $trackData['popularity'] ?? $popularity;

                        // Extract artist ID
                        $artistId = $trackData['artists']['items'][0]['uri'] ?? null;
                        if ($artistId) {
                            // Extract just the ID part from spotify:artist:ID format
                            $artistId = str_replace('spotify:artist:', '', $artistId);

                            \Log::info('💾 Save Track: Got track details', [
                                'popularity' => $popularity,
                                'artist_id' => $artistId
                            ]);

                            // Step 3: Fetch artist details for followers count
                            $artistResult = $this->rapidApiSpotifyService->getArtistById($artistId);

                            if ($artistResult['success'] && isset($artistResult['data'])) {
                                $artistData = $artistResult['data'];
                                $followers = $artistData['stats']['followers'] ?? $followers;

                                \Log::info('💾 Save Track: Got artist followers', [
                                    'followers' => $followers
                                ]);
                            }
                        }
                    }
                }
            }

            SavedTrack::saveTrack(
                $userId,
                $request->isrc,
                $request->track_name,
                $request->artist_name,
                $spotifyId,
                $request->label,
                $popularity,
                $followers,
                $request->release_date,
                $request->preview_url,
                $request->track_count,
                $request->is_single_track ?? true,
                $request->album_id
            );

            \Log::info('💾 Save Track: Successfully saved', [
                'track_name' => $request->track_name,
                'spotify_id' => $spotifyId,
                'popularity' => $popularity,
                'followers' => $followers
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Track saved successfully (expires in 24 hours)',
                'data' => [
                    'spotify_id' => $spotifyId,
                    'popularity' => $popularity,
                    'followers' => $followers
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Save track failed: ' . $e->getMessage(), [
                'track_name' => $request->track_name,
                'artist_name' => $request->artist_name,
                'isrc' => $request->isrc,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to save track: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Blacklist an artist by Spotify ID (primary artist)
     */
    public function blacklistArtist(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        $validator = Validator::make($request->all(), [
            'spotify_artist_id' => 'required|string',
            'artist_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        
        try {
            BlacklistedArtist::updateOrCreate(
                ['user_id' => $userId, 'spotify_artist_id' => $request->spotify_artist_id],
                ['artist_name' => $request->artist_name]
            );

            return response()->json([
                'success' => true,
                'message' => 'Artist blacklisted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to blacklist artist'
            ], 500);
        }
    }

    /**
     * Save an artist by Spotify ID (primary artist)
     */
    public function saveArtist(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        $validator = Validator::make($request->all(), [
            'spotify_artist_id' => 'required|string',
            'artist_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        
        try {
            SavedArtist::updateOrCreate(
                ['user_id' => $userId, 'spotify_artist_id' => $request->spotify_artist_id],
                ['artist_name' => $request->artist_name]
            );

            return response()->json([
                'success' => true,
                'message' => 'Artist saved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to save artist'
            ], 500);
        }
    }

    /**
     * Blacklist all currently displayed tracks that are not saved
     */
    public function blacklistUnsavedTracks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tracks' => 'required|array',
            'tracks.*.isrc' => 'required|string',
            'tracks.*.track_name' => 'required|string',
            'tracks.*.artist_name' => 'required|string',
            'tracks.*.spotify_id' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        $savedIsrcs = SavedTrack::getSavedIsrcs($userId);
        $blacklistedCount = 0;

        try {
            foreach ($request->tracks as $track) {
                // Skip if track is already saved
                if (in_array($track['isrc'], $savedIsrcs)) {
                    continue;
                }

                BlacklistedTrack::updateOrCreate(
                    ['user_id' => $userId, 'isrc' => $track['isrc']],
                    [
                        'track_name' => $track['track_name'],
                        'artist_name' => $track['artist_name'],
                        'spotify_id' => $track['spotify_id'] ?? null,
                    ]
                );

                $blacklistedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Blacklisted {$blacklistedCount} unsaved tracks",
                'blacklisted_count' => $blacklistedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to blacklist tracks'
            ], 500);
        }
    }

    /**
     * Get user's blacklisted tracks
     */
    public function getBlacklistedTracks(): JsonResponse
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        $tracks = BlacklistedTrack::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tracks
        ]);
    }

    /**
     * Get user's saved tracks (non-expired)
     */
    public function getSavedTracks(): JsonResponse
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        $tracks = SavedTrack::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tracks
        ]);
    }

    /**
     * Get user's blacklisted artists
     */
    public function getBlacklistedArtists(): JsonResponse
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        $artists = BlacklistedArtist::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $artists
        ]);
    }

    /**
     * Get user's saved artists
     */
    public function getSavedArtists(): JsonResponse
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }
        $artists = SavedArtist::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $artists
        ]);
    }

    /**
     * Remove a track from blacklist
     */
    public function removeFromBlacklist(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        // Handle both query parameters and request body
        $data = array_merge($request->query(), $request->all());

        $validator = Validator::make($data, [
            'isrc' => 'required|string',
            'track_name' => 'sometimes|string',
            'artist_name' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $query = BlacklistedTrack::where('user_id', $userId);

            // Try to match by ISRC first (most reliable)
            if (!empty($data['isrc']) && $data['isrc'] !== 'undefined') {
                $query->where('isrc', $data['isrc']);
            } else {
                // Fallback to track_name + artist_name matching if ISRC is not available
                if (!empty($data['track_name']) && !empty($data['artist_name'])) {
                    $query->where('track_name', $data['track_name'])
                          ->where('artist_name', $data['artist_name']);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Either ISRC or both track_name and artist_name are required'
                    ], 422);
                }
            }

            $deleted = $query->delete();

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Track removed from blacklist' : 'Track not found in blacklist'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to remove track from blacklist'
            ], 500);
        }
    }

    /**
     * Remove an artist from blacklist
     */
    public function removeArtistFromBlacklist(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->missingTablesResponse();
        }

        // Handle both query parameters and request body
        $data = array_merge($request->query(), $request->all());

        $validator = Validator::make($data, [
            'spotify_artist_id' => 'required|string',
            'artist_name' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $query = BlacklistedArtist::where('user_id', $userId);

            // Match by spotify_artist_id (primary key)
            if (!empty($data['spotify_artist_id'])) {
                $query->where('spotify_artist_id', $data['spotify_artist_id']);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'spotify_artist_id is required'
                ], 422);
            }

            $deleted = $query->delete();

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Artist removed from blacklist' : 'Artist not found in blacklist'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to remove artist from blacklist'
            ], 500);
        }
    }

    /**
     * Get Spotify track details by track ID
     */
    public function getSpotifyTrack(Request $request, string $trackId): JsonResponse
    {
        $validator = Validator::make(['track_id' => $trackId], [
            'track_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if this looks like a Koel UUID instead of a Spotify ID
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $trackId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid Spotify track ID format. Please provide a valid Spotify track ID.',
                    'code' => 'INVALID_SPOTIFY_ID'
                ], 400);
            }

            // Check cache first
            $cachedData = SpotifyCache::getCached($trackId, 'track');
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedData
                ]);
            }

            $trackDetails = $this->spotifyService->getTrackDetails($trackId);

            if (!$trackDetails) {
                return response()->json([
                    'success' => false,
                    'error' => 'Track not found'
                ], 404);
            }

            // Cache the result
            SpotifyCache::setCached($trackId, 'track', $trackDetails, 24);

            return response()->json([
                'success' => true,
                'data' => $trackDetails
            ]);
        } catch (\Exception $e) {
            \Log::error('Spotify track details error', [
                'message' => $e->getMessage(),
                'track_id' => $trackId
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch track details'
            ], 500);
        }
    }

    /**
     * Get Spotify artist details by artist ID
     */
    public function getSpotifyArtist(Request $request, string $artistId): JsonResponse
    {
        $validator = Validator::make(['artist_id' => $artistId], [
            'artist_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check cache first
            $cachedData = SpotifyCache::getCached($artistId, 'artist');
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedData
                ]);
            }

            // Use the SpotifyService to get artist data
            $artistDetails = $this->spotifyService->getArtist($artistId);

            if (!$artistDetails) {
                return response()->json([
                    'success' => false,
                    'error' => 'Artist not found'
                ], 404);
            }

            // Cache the result
            SpotifyCache::setCached($artistId, 'artist', $artistDetails, 24);

            return response()->json([
                'success' => true,
                'data' => $artistDetails
            ]);
        } catch (\Exception $e) {
            \Log::error('Spotify artist details error', [
                'message' => $e->getMessage(),
                'artist_id' => $artistId
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch artist details'
            ], 500);
        }
    }

    /**
     * Get Spotify album details by album ID
     */
    public function getSpotifyAlbum(Request $request, string $albumId): JsonResponse
    {
        $validator = Validator::make(['album_id' => $albumId], [
            'album_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check cache first
            $cachedData = SpotifyCache::getCached($albumId, 'album');
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedData
                ]);
            }

            // Use the SpotifyService to get album data
            $albumDetails = $this->spotifyService->getAlbum($albumId);

            if (!$albumDetails) {
                return response()->json([
                    'success' => false,
                    'error' => 'Album not found'
                ], 404);
            }

            // Cache the result
            SpotifyCache::setCached($albumId, 'album', $albumDetails, 24);

            return response()->json([
                'success' => true,
                'data' => $albumDetails
            ]);
        } catch (\Exception $e) {
            \Log::error('Spotify album details error', [
                'message' => $e->getMessage(),
                'album_id' => $albumId
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch album details'
            ], 500);
        }
    }

    /**
     * Search for a track on Spotify
     */
    public function searchSpotifyTrack(Request $request): JsonResponse
    {
        // EMERGENCY DISABLE: This endpoint was causing infinite API loops and costs
        \Log::warning('🚨 [SPOTIFY ENDPOINT] searchSpotifyTrack called - DISABLED to prevent API costs', [
            'query' => $request->get('q', 'no query'),
            'limit' => $request->get('limit', 'no limit'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Spotify search temporarily disabled to prevent API costs'
        ], 503);
    }
}