<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedTrack;
use App\Models\SavedTrack;
use App\Models\BlacklistedArtist;
use App\Models\SavedArtist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class MusicPreferencesController extends Controller
{
    /**
     * Check if all required tables exist
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('blacklisted_tracks') && 
               Schema::hasTable('saved_tracks') && 
               Schema::hasTable('blacklisted_artists') && 
               Schema::hasTable('saved_artists');
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
     * Save a track by ISRC (24-hour expiration)
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
            SavedTrack::saveTrack(
                $userId,
                $request->isrc,
                $request->track_name,
                $request->artist_name,
                $request->spotify_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Track saved successfully (expires in 24 hours)'
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
        $validator = Validator::make($request->all(), [
            'isrc' => 'required|string',
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
        $deleted = BlacklistedTrack::where('user_id', $userId)
            ->where('isrc', $request->isrc)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'Track removed from blacklist' : 'Track not found in blacklist'
        ]);
    }

    /**
     * Remove an artist from blacklist
     */
    public function removeArtistFromBlacklist(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'spotify_artist_id' => 'required|string',
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
        $deleted = BlacklistedArtist::where('user_id', $userId)
            ->where('spotify_artist_id', $request->spotify_artist_id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'Artist removed from blacklist' : 'Artist not found in blacklist'
        ]);
    }
}