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

            // Try RapidAPI Spotify first (primary)
            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                Log::info('🔍 [SIMILAR ARTISTS] Attempting RapidAPI Spotify search (primary)', [
                    'query' => $query,
                    'limit' => $limit
                ]);
                
                $artists = $this->rapidApiSpotifyService->searchArtists($query, $limit);
                
                if (!empty($artists)) {
                    // Remove duplicates from primary results
                    $deduplicatedArtists = $this->removeDuplicateArtists($artists);
                    
                    Log::info('🔍 [SIMILAR ARTISTS] RapidAPI Spotify search successful', [
                        'query' => $query,
                        'original_count' => count($artists),
                        'deduplicated_count' => count($deduplicatedArtists),
                        'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $deduplicatedArtists), 0, 3)
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $deduplicatedArtists
                    ]);
                } else {
                    Log::warning('🔍 [SIMILAR ARTISTS] RapidAPI Spotify search returned no results', [
                        'query' => $query
                    ]);
                }
            } else {
                Log::info('🔍 [SIMILAR ARTISTS] RapidAPI Spotify not available', [
                    'enabled' => RapidApiSpotifyService::enabled(),
                    'has_service' => !!$this->rapidApiSpotifyService
                ]);
            }

            // Try RapidAPI Spotify-web2 as Backup 1
            Log::info('🔍 [SIMILAR ARTISTS] Attempting RapidAPI Spotify-web2 search (Backup 1)');
            $unlimitedArtists = $this->searchWithUnlimitedAPI($query, $limit);
            if (!empty($unlimitedArtists)) {
                // Remove duplicates from backup results
                $deduplicatedUnlimitedArtists = $this->removeDuplicateArtists($unlimitedArtists);

                Log::info('🔍 [SIMILAR ARTISTS] RapidAPI Spotify-web2 search successful', [
                    'query' => $query,
                    'original_count' => count($unlimitedArtists),
                    'deduplicated_count' => count($deduplicatedUnlimitedArtists),
                    'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $deduplicatedUnlimitedArtists), 0, 3)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $deduplicatedUnlimitedArtists
                ]);
            }

            // Try Spotify23 as Backup 2 (final fallback)
            Log::info('🔍 [SIMILAR ARTISTS] Attempting Spotify23 search (Backup 2)');
            $spotify23Artists = $this->searchWithSpotify23($query, $limit);
            if (!empty($spotify23Artists)) {
                // Remove duplicates from Spotify23 results
                $deduplicatedSpotify23Artists = $this->removeDuplicateArtists($spotify23Artists);

                Log::info('🔍 [SIMILAR ARTISTS] Spotify23 search successful', [
                    'query' => $query,
                    'original_count' => count($spotify23Artists),
                    'deduplicated_count' => count($deduplicatedSpotify23Artists),
                    'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $deduplicatedSpotify23Artists), 0, 3)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $deduplicatedSpotify23Artists
                ]);
            }

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
     * Get similar artists by Spotify Artist ID
     */
    public function getSimilarArtists(Request $request): JsonResponse
    {
        $artistId = $request->get('artist_id', '');
        $limit = $request->get('limit', 20);

        if (empty(trim($artistId))) {
            return response()->json([
                'success' => false,
                'message' => 'Artist ID parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('🎵 [SIMILAR ARTISTS] Getting similar artists', [
                'artist_id' => $artistId,
                'limit' => $limit,
                'rapidapi_enabled' => RapidApiSpotifyService::enabled()
            ]);

            // Try RapidAPI Spotify81 first (Primary)
            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                Log::info('🎵 [SIMILAR ARTISTS] Attempting RapidAPI Spotify81 similar artists (Primary)', [
                    'artist_id' => $artistId,
                    'limit' => $limit
                ]);

                $similarArtists = $this->rapidApiSpotifyService->getSimilarArtists($artistId, $limit);

                if (!empty($similarArtists)) {
                    Log::info('🎵 [SIMILAR ARTISTS] RapidAPI Spotify81 similar artists successful', [
                        'artist_id' => $artistId,
                        'count' => count($similarArtists),
                        'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $similarArtists), 0, 3)
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $similarArtists
                    ]);
                } else {
                    Log::warning('🎵 [SIMILAR ARTISTS] RapidAPI Spotify81 similar artists returned no results', [
                        'artist_id' => $artistId
                    ]);
                }
            } else {
                Log::info('🎵 [SIMILAR ARTISTS] RapidAPI Spotify81 similar artists not available', [
                    'enabled' => RapidApiSpotifyService::enabled(),
                    'has_service' => !!$this->rapidApiSpotifyService
                ]);
            }

            // Try RapidAPI Spotify-web2 as Backup 1
            Log::info('🎵 [SIMILAR ARTISTS] Attempting RapidAPI Spotify-web2 similar artists (Backup 1)');
            $unlimitedSimilar = $this->getSimilarArtistsWithUnlimitedAPI($artistId, $limit);
            if (!empty($unlimitedSimilar)) {
                Log::info('🎵 [SIMILAR ARTISTS] RapidAPI Spotify-web2 similar artists successful', [
                    'artist_id' => $artistId,
                    'count' => count($unlimitedSimilar)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $unlimitedSimilar
                ]);
            }

            // Try Spotify23 as Backup 2 (final fallback)
            Log::info('🎵 [SIMILAR ARTISTS] Attempting Spotify23 similar artists (Backup 2)');
            $spotify23Similar = $this->getSimilarArtistsWithSpotify23($artistId, $limit);
            if (!empty($spotify23Similar)) {
                Log::info('🎵 [SIMILAR ARTISTS] Spotify23 similar artists successful', [
                    'artist_id' => $artistId,
                    'count' => count($spotify23Similar)
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $spotify23Similar
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'All RapidAPI similar artists services failed',
                'data' => []
            ]);

        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] Failed to get similar artists', [
                'artist_id' => $artistId,
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

            // Try RapidAPI Spotify81 first (Primary)
            if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
                Log::info('📊 [SIMILAR ARTISTS] Attempting RapidAPI Spotify81 batch followers (Primary)', [
                    'artist_count' => count($artistIds),
                    'artist_ids' => $artistIds
                ]);

                $followersData = $this->rapidApiSpotifyService->getBatchArtistFollowers($artistIds);

                if (!empty($followersData)) {
                    Log::info('📊 [SIMILAR ARTISTS] RapidAPI Spotify81 batch followers successful', [
                        'requested_count' => count($artistIds),
                        'returned_count' => count($followersData),
                        'success_rate' => round((count($followersData) / count($artistIds)) * 100, 1) . '%'
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $followersData
                    ]);
                } else {
                    Log::warning('📊 [SIMILAR ARTISTS] RapidAPI Spotify81 batch followers returned no data', [
                        'artist_count' => count($artistIds)
                    ]);
                }
            } else {
                Log::info('📊 [SIMILAR ARTISTS] RapidAPI Spotify81 batch followers not available', [
                    'enabled' => RapidApiSpotifyService::enabled(),
                    'has_service' => !!$this->rapidApiSpotifyService
                ]);
            }

            // Try Spotify-web2 as Backup 1
            Log::info('📊 [SIMILAR ARTISTS] Attempting Spotify-web2 batch followers (Backup 1)');
            $web2Followers = $this->getBatchArtistFollowersWithSpotifyWeb2($artistIds);
            if (!empty($web2Followers)) {
                Log::info('📊 [SIMILAR ARTISTS] Spotify-web2 batch followers successful', [
                    'requested_count' => count($artistIds),
                    'returned_count' => count($web2Followers),
                    'success_rate' => round((count($web2Followers) / count($artistIds)) * 100, 1) . '%'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $web2Followers
                ]);
            }

            // Try Spotify23 as Backup 2 (final fallback)
            Log::info('📊 [SIMILAR ARTISTS] Attempting Spotify23 batch followers (Backup 2)');
            $spotify23Followers = $this->getBatchArtistFollowersWithSpotify23($artistIds);
            if (!empty($spotify23Followers)) {
                Log::info('📊 [SIMILAR ARTISTS] Spotify23 batch followers successful', [
                    'requested_count' => count($artistIds),
                    'returned_count' => count($spotify23Followers),
                    'success_rate' => round((count($spotify23Followers) / count($artistIds)) * 100, 1) . '%'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $spotify23Followers
                ]);
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

            // Try RapidAPI Spotify-web2 as Backup 1
            if (!empty($artistId)) {
                Log::info('🎵 [SIMILAR ARTISTS] Attempting RapidAPI Spotify-web2 preview (Backup 1)');
                $unlimitedTracks = $this->getPreviewWithUnlimitedAPI($artistId, $limit);

                // Filter out remixes and non-playable tracks
                $playableBackup = array_values(array_filter($unlimitedTracks, function ($t) {
                    $isPlayable = !empty($t['external_url']) || !empty($t['preview_url']);
                    $isNotRemix = !$this->isRemixTrack($t['name'] ?? '');
                    return $isPlayable && $isNotRemix;
                }));

                if (!empty($playableBackup)) {
                    Log::info('🎵 [SIMILAR ARTISTS] RapidAPI Spotify-web2 preview successful', [
                        'artist_id' => $artistId,
                        'tracks_count' => count($playableBackup),
                        'has_playable' => true
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'artist' => [
                                'id' => $artistId,
                                'name' => $artistName
                            ],
                            'tracks' => $playableBackup
                        ]
                    ]);
                }
            }

            // Try Spotify23 as Backup 2
            if (!empty($artistId)) {
                Log::info('🎵 [SIMILAR ARTISTS] Attempting Spotify23 preview (Backup 2)');
                $spotify23Tracks = $this->getPreviewWithSpotify23($artistId, $limit);

                // Filter out remixes and non-playable tracks
                $playableSpotify23 = array_values(array_filter($spotify23Tracks, function ($t) {
                    $isPlayable = !empty($t['external_url']) || !empty($t['preview_url']);
                    $isNotRemix = !$this->isRemixTrack($t['name'] ?? '');
                    return $isPlayable && $isNotRemix;
                }));

                if (!empty($playableSpotify23)) {
                    Log::info('🎵 [SIMILAR ARTISTS] Spotify23 preview successful', [
                        'artist_id' => $artistId,
                        'tracks_count' => count($playableSpotify23),
                        'has_playable' => true
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'artist' => [
                                'id' => $artistId,
                                'name' => $artistName
                            ],
                            'tracks' => $playableSpotify23
                        ]
                    ]);
                }
            }

            // Fallback to regular Spotify client if we have artist name OR only artistId
            if ((!empty($artistName) || !empty($artistId)) && SpotifyService::enabled()) {
                Log::info('🎵 [SIMILAR ARTISTS] Falling back to regular Spotify client');
                
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
                    $spotifyArtist = null;
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
     * Search with RapidAPI UnlimitedAPI as backup
     */
    private function searchWithUnlimitedAPI(string $query, int $limit): array
    {
        try {
            Log::info('🔍 [SIMILAR ARTISTS] Starting UnlimitedAPI search', [
                'query' => $query,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify-web2.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get('https://spotify-web2.p.rapidapi.com/search', [
                'q' => $query,
                'type' => 'artists',
                'limit' => min($limit, 50)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists']['items'])) {
                    $artists = $this->formatUnlimitedAPIArtists($data['artists']['items']);
                    Log::info('🔍 [SIMILAR ARTISTS] UnlimitedAPI search successful', [
                        'query' => $query,
                        'count' => count($artists)
                    ]);
                    return $artists;
                }
            }

            Log::warning('🔍 [SIMILAR ARTISTS] UnlimitedAPI search failed', [
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🔍 [SIMILAR ARTISTS] UnlimitedAPI search exception', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format UnlimitedAPI artists to match expected format
     */
    private function formatUnlimitedAPIArtists(array $artists): array
    {
        return array_map(function ($artist) {
            return [
                'id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? 'Unknown Artist',
                'followers' => $artist['followers']['total'] ?? 0,
                'popularity' => $artist['popularity'] ?? 0,
                'genres' => $artist['genres'] ?? [],
                'external_url' => $artist['external_urls']['spotify'] ?? null,
                'images' => $artist['images'] ?? []
            ];
        }, $artists);
    }

    /**
     * Get similar artists with RapidAPI UnlimitedAPI as backup
     */
    private function getSimilarArtistsWithUnlimitedAPI(string $artistId, int $limit): array
    {
        try {
            Log::info('🎵 [SIMILAR ARTISTS] Starting UnlimitedAPI similar artists', [
                'artist_id' => $artistId,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify-web2.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify-web2.p.rapidapi.com/artist_related", [
                'id' => $artistId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists'])) {
                    $artists = $this->formatUnlimitedAPIArtists(array_slice($data['artists'], 0, $limit));
                    Log::info('🎵 [SIMILAR ARTISTS] UnlimitedAPI similar artists successful', [
                        'artist_id' => $artistId,
                        'count' => count($artists)
                    ]);
                    return $artists;
                }
            }

            Log::warning('🎵 [SIMILAR ARTISTS] UnlimitedAPI similar artists failed', [
                'artist_id' => $artistId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] UnlimitedAPI similar artists exception', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get preview with RapidAPI UnlimitedAPI as backup
     */
    private function getPreviewWithUnlimitedAPI(string $artistId, int $limit): array
    {
        try {
            Log::info('🎵 [SIMILAR ARTISTS] Starting UnlimitedAPI preview', [
                'artist_id' => $artistId,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify-web2.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify-web2.p.rapidapi.com/artist_overview", [
                'id' => $artistId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['artist']['discography']['popularReleases']['items'])) {
                    $popularReleases = $data['data']['artist']['discography']['popularReleases']['items'];
                    
                    // Find first SINGLE type release
                    foreach ($popularReleases as $item) {
                        if (isset($item['releases']['items'][0])) {
                            $release = $item['releases']['items'][0];
                            if (isset($release['type']) && strtoupper($release['type']) === 'SINGLE') {
                                $shareUrl = $release['sharingInfo']['shareUrl'] ?? null;
                                $trackName = $release['name'] ?? 'Unknown Track';
                                
                                $tracks = [[
                                    'id' => null,
                                    'name' => $trackName,
                                    'share_url' => $shareUrl,
                                    'external_url' => $shareUrl,
                                    'label' => $release['label'] ?? null
                                ]];
                                
                                Log::info('🎵 [SIMILAR ARTISTS] UnlimitedAPI preview successful', [
                                    'artist_id' => $artistId,
                                    'track_name' => $trackName
                                ]);
                                return $tracks;
                            }
                        }
                    }
                }
            }

            Log::warning('🎵 [SIMILAR ARTISTS] UnlimitedAPI preview failed', [
                'artist_id' => $artistId,
                'status' => $response->status()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] UnlimitedAPI preview exception', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
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
            } else {
                Log::warning("🎵 Similar Artists: Failed to get oEmbed", [
                    'spotify_track_id' => $spotifyTrackId,
                    'status' => $response->status()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Similar Artists: Spotify oEmbed error', [
                'track_id' => $spotifyTrackId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Search with Spotify23 as third backup
     */
    private function searchWithSpotify23(string $query, int $limit): array
    {
        try {
            Log::info('🔍 [SIMILAR ARTISTS] Starting Spotify23 search', [
                'query' => $query,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify23.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get('https://spotify23.p.rapidapi.com/search/', [
                'q' => $query,
                'type' => 'multi',
                'offset' => 0,
                'limit' => min($limit, 10),
                'numberOfTopResults' => 5
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists']['items'])) {
                    $artists = $this->formatSpotify23Artists($data['artists']['items']);
                    Log::info('🔍 [SIMILAR ARTISTS] Spotify23 search successful', [
                        'query' => $query,
                        'count' => count($artists)
                    ]);
                    return $artists;
                }
            }

            Log::warning('🔍 [SIMILAR ARTISTS] Spotify23 search failed', [
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🔍 [SIMILAR ARTISTS] Spotify23 search exception', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get similar artists with Spotify23 as third backup
     */
    private function getSimilarArtistsWithSpotify23(string $artistId, int $limit): array
    {
        try {
            Log::info('🎵 [SIMILAR ARTISTS] Starting Spotify23 similar artists', [
                'artist_id' => $artistId,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify23.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify23.p.rapidapi.com/artist_related/", [
                'id' => $artistId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists'])) {
                    $artists = $this->formatSpotify23Artists(array_slice($data['artists'], 0, $limit));
                    Log::info('🎵 [SIMILAR ARTISTS] Spotify23 similar artists successful', [
                        'artist_id' => $artistId,
                        'count' => count($artists)
                    ]);
                    return $artists;
                }
            }

            Log::warning('🎵 [SIMILAR ARTISTS] Spotify23 similar artists failed', [
                'artist_id' => $artistId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] Spotify23 similar artists exception', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get batch artist followers with Spotify-web2 as backup 1
     */
    private function getBatchArtistFollowersWithSpotifyWeb2(array $artistIds): array
    {
        try {
            Log::info('📊 [SIMILAR ARTISTS] Starting Spotify-web2 batch followers', [
                'artist_count' => count($artistIds),
                'artist_ids' => $artistIds
            ]);

            // Join artist IDs with comma for batch request
            $idsParam = implode(',', $artistIds);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify-web2.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify-web2.p.rapidapi.com/artists/", [
                'ids' => $idsParam
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists'])) {
                    $followersData = [];
                    foreach ($data['artists'] as $artist) {
                        if (isset($artist['id']) && isset($artist['followers']['total'])) {
                            $followersData[$artist['id']] = $artist['followers']['total'];
                        }
                    }

                    Log::info('📊 [SIMILAR ARTISTS] Spotify-web2 batch followers successful', [
                        'requested_count' => count($artistIds),
                        'returned_count' => count($followersData)
                    ]);
                    return $followersData;
                }
            }

            Log::warning('📊 [SIMILAR ARTISTS] Spotify-web2 batch followers failed', [
                'artist_count' => count($artistIds),
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('📊 [SIMILAR ARTISTS] Spotify-web2 batch followers exception', [
                'artist_count' => count($artistIds),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get batch artist followers with Spotify23 as backup 2
     */
    private function getBatchArtistFollowersWithSpotify23(array $artistIds): array
    {
        try {
            Log::info('📊 [SIMILAR ARTISTS] Starting Spotify23 batch followers', [
                'artist_count' => count($artistIds),
                'artist_ids' => $artistIds
            ]);

            // Join artist IDs with comma for batch request
            $idsParam = implode(',', $artistIds);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify23.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify23.p.rapidapi.com/artists/", [
                'ids' => $idsParam
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['artists'])) {
                    $followersData = [];
                    foreach ($data['artists'] as $artist) {
                        if (isset($artist['id']) && isset($artist['followers']['total'])) {
                            $followersData[$artist['id']] = $artist['followers']['total'];
                        }
                    }

                    Log::info('📊 [SIMILAR ARTISTS] Spotify23 batch followers successful', [
                        'requested_count' => count($artistIds),
                        'returned_count' => count($followersData)
                    ]);
                    return $followersData;
                }
            }

            Log::warning('📊 [SIMILAR ARTISTS] Spotify23 batch followers failed', [
                'artist_count' => count($artistIds),
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('📊 [SIMILAR ARTISTS] Spotify23 batch followers exception', [
                'artist_count' => count($artistIds),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get preview with Spotify23 as third backup
     */
    private function getPreviewWithSpotify23(string $artistId, int $limit): array
    {
        try {
            Log::info('🎵 [SIMILAR ARTISTS] Starting Spotify23 preview', [
                'artist_id' => $artistId,
                'limit' => $limit
            ]);

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-RapidAPI-Key' => config('services.rapidapi.key'),
                'X-RapidAPI-Host' => 'spotify23.p.rapidapi.com'
            ])
            ->timeout(30)
            ->get("https://spotify23.p.rapidapi.com/artist_overview/", [
                'id' => $artistId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['artist']['discography']['popularReleases']['items'])) {
                    $popularReleases = $data['data']['artist']['discography']['popularReleases']['items'];

                    // Find first SINGLE type release
                    foreach ($popularReleases as $item) {
                        if (isset($item['releases']['items'][0])) {
                            $release = $item['releases']['items'][0];
                            if (isset($release['type']) && strtoupper($release['type']) === 'SINGLE') {
                                $shareUrl = $release['sharingInfo']['shareUrl'] ?? null;
                                $trackName = $release['name'] ?? 'Unknown Track';

                                $tracks = [[
                                    'id' => null,
                                    'name' => $trackName,
                                    'share_url' => $shareUrl,
                                    'external_url' => $shareUrl,
                                    'label' => $release['label'] ?? null
                                ]];

                                Log::info('🎵 [SIMILAR ARTISTS] Spotify23 preview successful', [
                                    'artist_id' => $artistId,
                                    'track_name' => $trackName
                                ]);
                                return $tracks;
                            }
                        }
                    }
                }
            }

            Log::warning('🎵 [SIMILAR ARTISTS] Spotify23 preview failed', [
                'artist_id' => $artistId,
                'status' => $response->status()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('🎵 [SIMILAR ARTISTS] Spotify23 preview exception', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format Spotify23 artists to match expected format
     */
    private function formatSpotify23Artists(array $artists): array
    {
        return array_map(function ($artist) {
            return [
                'id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? 'Unknown Artist',
                'followers' => $artist['followers']['total'] ?? 0,
                'popularity' => $artist['popularity'] ?? 0,
                'genres' => $artist['genres'] ?? [],
                'external_url' => $artist['external_urls']['spotify'] ?? null,
                'images' => $artist['images'] ?? []
            ];
        }, $artists);
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