<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\GenreByYearRequest;
use App\Services\SpotifyService;
use App\Services\RapidApiSpotifyService;
use App\Models\SavedTrack;
use App\Models\BlacklistedTrack;
use App\Models\BlacklistedArtist;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class GenreByYearController extends Controller
{
    public function __construct(
        private readonly SpotifyService $spotifyService,
        private ?RapidApiSpotifyService $rapidApiSpotifyService = null
    )
    {
    }

    public function __invoke(GenreByYearRequest $request)
    {
        if (!SpotifyService::enabled() && !RapidApiSpotifyService::enabled()) {
            return response()->json(['error' => 'Spotify integration is not enabled'], 503);
        }

        $genre = $request->validated('genre');
        $year = $request->validated('year');
        $popularityMin = $request->validated('popularity_min');
        $popularityMax = $request->validated('popularity_max');
        $followersMin = $request->validated('followers_min');
        $followersMax = $request->validated('followers_max');
        $offset = $request->validated('offset', 0);

        try {
            // Build Spotify search query: genre:"{genre}" year:{year} type:tracks limit:100
            $query = 'genre:"' . $genre . '"';
            
            if ($year) {
                $query .= " year:$year";
            }

            Log::info('Genre by Year search', [
                'query' => $query,
                'genre' => $genre,
                'year' => $year,
                'offset' => $offset,
                'popularity_range' => $popularityMin !== null || $popularityMax !== null ? [$popularityMin, $popularityMax] : null,
                'followers_range' => $followersMin !== null || $followersMax !== null ? [$followersMin, $followersMax] : null,
            ]);

            // Use RapidAPI Spotify if available, otherwise fall back to SpotifyService
            $searchResults = null;
            $tracks = [];
            
            if ($this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                // Calculate offset for API (Spotify API uses offset, not page number)
                // offset=0 for first page, offset=100 for second page, etc.
                $apiOffset = $offset * 100; // Each "offset" increment = 100 tracks
                $result = $this->rapidApiSpotifyService->searchTracks($query, 100, 'tracks', $apiOffset);
                
                // Log full response structure
                Log::info('RapidAPI search result', [
                    'success' => $result['success'] ?? false,
                    'has_data' => isset($result['data']),
                    'data_keys' => isset($result['data']) ? array_keys($result['data']) : [],
                    'has_tracks' => isset($result['data']['tracks']),
                    'tracks_structure' => isset($result['data']['tracks']) ? (isset($result['data']['tracks']['items']) ? 'items_array' : 'direct_array') : 'none',
                    'offset' => $offset,
                    'api_offset' => $apiOffset,
                ]);
                
                // Log first track's full structure for debugging
                if ($result['success'] && isset($result['data']['tracks'])) {
                    $firstTracks = isset($result['data']['tracks']['items']) 
                        ? ($result['data']['tracks']['items'] ?? [])
                        : ($result['data']['tracks'] ?? []);
                    
                    if (!empty($firstTracks)) {
                        $firstTrack = $firstTracks[0];
                        $firstTrackData = $firstTrack['data'] ?? $firstTrack;
                        $albumOfTrack = $firstTrackData['albumOfTrack'] ?? [];
                        
                        Log::info('First track full structure', [
                            'track_id' => $firstTrackData['id'] ?? 'none',
                            'track_name' => $firstTrackData['name'] ?? 'none',
                            'has_albumOfTrack' => isset($firstTrackData['albumOfTrack']),
                            'albumOfTrack_full' => $albumOfTrack,
                            'albumOfTrack_keys' => array_keys($albumOfTrack),
                            'albumOfTrack_date' => $albumOfTrack['date'] ?? 'NOT_SET',
                            'albumOfTrack_date_type' => isset($albumOfTrack['date']) ? gettype($albumOfTrack['date']) : 'NOT_SET',
                            'albumOfTrack_date_value' => isset($albumOfTrack['date']) 
                                ? (is_array($albumOfTrack['date']) 
                                    ? json_encode($albumOfTrack['date'], JSON_PRETTY_PRINT)
                                    : $albumOfTrack['date'])
                                : 'NOT_SET',
                        ]);
                    }
                }
                
                if ($result['success'] && isset($result['data']['tracks'])) {
                    $searchResults = $result['data'];
                    
                    // Handle both response structures:
                    // Official Spotify: data.tracks.items[]
                    // RapidAPI: data.tracks[]
                    $isOfficialStructure = isset($result['data']['tracks']['items']);
                    $tracks = $isOfficialStructure
                        ? ($result['data']['tracks']['items'] ?? [])
                        : ($result['data']['tracks'] ?? []);
                    
                    Log::info('Extracted tracks from response', [
                        'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi',
                        'track_count' => count($tracks),
                    ]);
                } else {
                    Log::warning('RapidAPI search failed or invalid structure', [
                        'success' => $result['success'] ?? false,
                        'error' => $result['error'] ?? 'No error message',
                        'has_data' => isset($result['data']),
                    ]);
                }
            }

            // Fallback to SpotifyService if RapidAPI failed or not available
            if (empty($tracks) && SpotifyService::enabled()) {
                // Note: SpotifyService searchTracks is disabled, so we'll rely on RapidAPI
                Log::warning('SpotifyService searchTracks is disabled, relying on RapidAPI');
            }

            if (empty($tracks)) {
                Log::info('No tracks found, returning empty array', [
                    'offset' => $offset,
                    'api_offset' => $offset * 100,
                ]);
                return response()->json(['tracks' => []]);
            }

            Log::info('Spotify search results', [
                'query' => $query,
                'total_results' => $searchResults['tracks']['total'] ?? count($tracks),
                'returned_items' => count($tracks),
                'offset' => $offset,
                'api_offset' => $offset * 100,
            ]);

            // Extract unique album IDs for batch fetching release dates and popularity
            $albumIds = [];
            foreach ($tracks as $trackWrapper) {
                $track = $trackWrapper['data'] ?? $trackWrapper;
                $albumOfTrack = $track['albumOfTrack'] ?? [];
                // Try both 'id' and extracting from 'uri'
                $albumId = $albumOfTrack['id'] ?? null;
                if (!$albumId && !empty($albumOfTrack['uri'])) {
                    $albumId = str_replace('spotify:album:', '', $albumOfTrack['uri']);
                }
                if (!empty($albumId)) {
                    $albumIds[] = $albumId;
                }
            }
            $albumIds = array_unique($albumIds);
            
            // Batch fetch album details for release dates
            // Use smaller batches (20 at a time) since RapidAPI may have limits
            // If batch fails, fall back to individual requests
            $albumDetailsMap = [];
            if (!empty($albumIds) && $this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                try {
                    // Try batch first, but use smaller chunks (20 IDs max per batch)
                    $batchSize = 20;
                    $batches = array_chunk($albumIds, $batchSize);
                    
                    foreach ($batches as $batchIndex => $batch) {
                        $batchData = $this->rapidApiSpotifyService->getBatchAlbums($batch);
                        if (!empty($batchData)) {
                            $albumDetailsMap = array_merge($albumDetailsMap, $batchData);
                        } else {
                            // If batch fails, fall back to individual requests for this batch
                            Log::info('💿 [GENRE BY YEAR] Batch failed, falling back to individual requests', [
                                'batch_index' => $batchIndex,
                                'batch_size' => count($batch)
                            ]);
                            
                            foreach ($batch as $albumId) {
                                try {
                                    $albumResult = $this->rapidApiSpotifyService->getAlbumById($albumId);
                                    if ($albumResult['success'] && isset($albumResult['data'])) {
                                        $album = $albumResult['data'];
                                        $albumDetailsMap[$albumId] = [
                                            'id' => $album['id'] ?? $albumId,
                                            'name' => $album['name'] ?? null,
                                            'release_date' => $album['release_date'] ?? null,
                                            'popularity' => $album['popularity'] ?? null,
                                            'images' => $album['images'] ?? [],
                                            'external_urls' => $album['external_urls'] ?? [],
                                        ];
                                    }
                                    // Rate limiting: 5 req/sec = 200ms delay between individual requests
                                    usleep(200000);
                                } catch (\Exception $e) {
                                    Log::warning('💿 [GENRE BY YEAR] Failed to fetch individual album', [
                                        'album_id' => $albumId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                        
                        // Rate limiting: 200ms delay between batches
                        if ($batchIndex < count($batches) - 1) {
                            usleep(200000);
                        }
                    }
                    
                    Log::info('💿 [GENRE BY YEAR] Fetched album details', [
                        'album_count' => count($albumDetailsMap),
                        'unique_album_ids' => count($albumIds),
                        'timestamp' => now()->toISOString()
                    ]);
                } catch (\Exception $e) {
                    Log::warning('💿 [GENRE BY YEAR] Failed to fetch album details', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Format tracks (pass album details map)
            $formattedTracks = $this->formatTracks($tracks, $albumDetailsMap);

            // Extract unique track IDs for batch fetching popularity
            $trackIds = [];
            foreach ($formattedTracks as $track) {
                if (!empty($track['spotify_id'])) {
                    $trackIds[] = $track['spotify_id'];
                }
            }
            $trackIds = array_unique($trackIds);

            // Batch fetch track details to get popularity (split if >50 IDs)
            $trackDetailsMap = [];
            if (!empty($trackIds) && $this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                try {
                    if (count($trackIds) > 50) {
                        // Split into batches of 50
                        $batches = array_chunk($trackIds, 50);
                        foreach ($batches as $batch) {
                            $batchData = $this->rapidApiSpotifyService->getBatchTracks($batch);
                            $trackDetailsMap = array_merge($trackDetailsMap, $batchData);
                            // Rate limiting: 5 req/sec = 200ms delay between batches
                            if (count($batches) > 1) {
                                usleep(200000);
                            }
                        }
                    } else {
                        $trackDetailsMap = $this->rapidApiSpotifyService->getBatchTracks($trackIds);
                    }
                    
                    Log::info('🎵 [GENRE BY YEAR] Fetched track details for popularity', [
                        'track_count' => count($trackDetailsMap),
                        'unique_track_ids' => count($trackIds),
                        'timestamp' => now()->toISOString()
                    ]);
                } catch (\Exception $e) {
                    Log::warning('🎵 [GENRE BY YEAR] Failed to fetch track details', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Update tracks with fetched popularity
            $popularityUpdatedCount = 0;
            $popularityZeroCount = 0;
            foreach ($formattedTracks as &$track) {
                $trackId = $track['spotify_id'] ?? null;
                if (!empty($trackId) && isset($trackDetailsMap[$trackId])) {
                    $trackDetails = $trackDetailsMap[$trackId];
                    // Update popularity from batch fetch (0 is a valid value, so check isset not > 0)
                    if (isset($trackDetails['popularity'])) {
                        $oldPopularity = $track['popularity'] ?? 'NOT_SET';
                        $newPopularity = (int) $trackDetails['popularity'];
                        $track['popularity'] = $newPopularity;
                        $popularityUpdatedCount++;
                        if ($newPopularity === 0) {
                            $popularityZeroCount++;
                        }
                        // Log first few updates for debugging
                        if ($popularityUpdatedCount <= 3) {
                            Log::info('🎵 [GENRE BY YEAR] Updating track popularity', [
                                'track_id' => $trackId,
                                'track_name' => $track['track_name'] ?? 'unknown',
                                'old_popularity' => $oldPopularity,
                                'new_popularity' => $newPopularity,
                                'track_details_keys' => array_keys($trackDetails),
                            ]);
                        }
                    }
                    // Also update preview_url if available
                    if (isset($trackDetails['preview_url']) && !empty($trackDetails['preview_url'])) {
                        $track['preview_url'] = $trackDetails['preview_url'];
                    }
                }
            }
            unset($track); // Break reference
            
            Log::info('🎵 [GENRE BY YEAR] Popularity update summary', [
                'total_tracks' => count($formattedTracks),
                'tracks_in_map' => count($trackDetailsMap),
                'popularity_updated' => $popularityUpdatedCount,
                'popularity_zero_count' => $popularityZeroCount,
            ]);

            // Filter out banned tracks and banned artists BEFORE fetching followers
            // (This reduces the number of API calls needed)
            $formattedTracks = $this->filterBannedTracksAndArtists($formattedTracks);

            // Extract unique artist IDs from remaining tracks
            $artistIds = [];
            foreach ($formattedTracks as $track) {
                if (!empty($track['artist_id'])) {
                    $artistIds[] = $track['artist_id'];
                }
            }
            $artistIds = array_unique($artistIds);

            // Fetch followers data in batch (split if >50 IDs)
            $followersData = [];
            if (!empty($artistIds) && $this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
                try {
                    if (count($artistIds) > 50) {
                        // Split into batches of 50
                        $batches = array_chunk($artistIds, 50);
                        foreach ($batches as $batchIndex => $batch) {
                            $batchData = $this->rapidApiSpotifyService->getBatchArtistFollowers($batch);
                            $followersData = array_merge($followersData, $batchData);
                            // Rate limiting: 5 req/sec = 200ms delay between batches
                            if ($batchIndex < count($batches) - 1) {
                                usleep(200000);
                            }
                        }
                    } else {
                        $followersData = $this->rapidApiSpotifyService->getBatchArtistFollowers($artistIds);
                    }
                    
                    Log::info('📊 [GENRE BY YEAR] Fetched followers data', [
                        'artist_count' => count($followersData),
                        'unique_artist_ids' => count($artistIds),
                        'timestamp' => now()->toISOString()
                    ]);
                } catch (\Exception $e) {
                    Log::warning('📊 [GENRE BY YEAR] Failed to fetch followers data', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add followers and use artist popularity as fallback if track popularity is 0
            // IMPORTANT: Do this BEFORE filtering by popularity so we can use artist popularity
            foreach ($formattedTracks as &$track) {
                $artistId = $track['artist_id'] ?? null;
                $followers = 0;
                $artistPopularity = null;

                if (!empty($artistId) && isset($followersData[$artistId])) {
                    $followers = $followersData[$artistId]['followers'] ?? 0;
                    $artistPopularity = $followersData[$artistId]['popularity'] ?? null;
                }

                $track['followers'] = $followers;
                
                // If track popularity is 0 or not set, use artist popularity as fallback
                // This ensures we have a popularity value for filtering
                if (($track['popularity'] ?? 0) === 0 && $artistPopularity !== null) {
                    $track['popularity'] = (int) $artistPopularity;
                    Log::info('🎵 [GENRE BY YEAR] Using artist popularity as fallback', [
                        'track_id' => $track['spotify_id'] ?? 'unknown',
                        'track_name' => $track['track_name'] ?? 'unknown',
                        'artist_id' => $artistId,
                        'artist_popularity' => $artistPopularity,
                    ]);
                }
            }
            unset($track); // Break reference

            // Filter by popularity if provided (AFTER artist popularity fallback)
            if ($popularityMin !== null || $popularityMax !== null) {
                $beforeFilterCount = count($formattedTracks);
                $formattedTracks = array_filter($formattedTracks, function($track) use ($popularityMin, $popularityMax) {
                    $popularity = $track['popularity'] ?? 0;
                    if ($popularityMin !== null && $popularity < $popularityMin) {
                        return false;
                    }
                    if ($popularityMax !== null && $popularity > $popularityMax) {
                        return false;
                    }
                    return true;
                });
                $formattedTracks = array_values($formattedTracks); // Re-index array
                
                Log::info('🎵 [GENRE BY YEAR] Popularity filter applied', [
                    'before_filter' => $beforeFilterCount,
                    'after_filter' => count($formattedTracks),
                    'popularity_min' => $popularityMin,
                    'popularity_max' => $popularityMax,
                ]);
            }

            // Filter by followers if provided
            if ($followersMin !== null || $followersMax !== null) {
                $formattedTracks = array_filter($formattedTracks, function($track) use ($followersMin, $followersMax) {
                    $followers = $track['followers'] ?? 0;
                    if ($followersMin !== null && $followers < $followersMin) {
                        return false;
                    }
                    if ($followersMax !== null && $followers > $followersMax) {
                        return false;
                    }
                    return true;
                });
                $formattedTracks = array_values($formattedTracks); // Re-index array
            }

            // Add user preference status (saved/banned)
            $formattedTracks = $this->addUserPreferenceStatus($formattedTracks);

            // Remove popularity from response (not displayed in table, only used for filtering)
            foreach ($formattedTracks as &$track) {
                unset($track['popularity']);
            }
            unset($track); // Break reference

            // Ensure array is properly indexed (sequential keys) for JSON encoding
            $formattedTracks = array_values($formattedTracks);

            Log::info('Final response being sent', [
                'track_count' => count($formattedTracks),
                'response_structure' => ['tracks' => 'array of ' . count($formattedTracks) . ' items'],
                'is_array' => is_array($formattedTracks),
                'array_keys_sample' => array_slice(array_keys($formattedTracks), 0, 5),
            ]);

            return response()->json(['tracks' => $formattedTracks]);

        } catch (\Exception $e) {
            Log::error('Genre by Year search failed', [
                'genre' => $genre,
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    private function formatTracks(array $tracks, array $albumDetailsMap = [], ?string $searchYear = null): array
    {
        // Log first track structure for debugging (before processing)
        if (!empty($tracks)) {
            $firstTrack = $tracks[0];
            $sampleTrack = $firstTrack['data'] ?? $firstTrack;
            $albumOfTrack = $sampleTrack['albumOfTrack'] ?? [];
            Log::info('Track structure sample', [
                'has_artists_items' => isset($sampleTrack['artists']['items']),
                'has_albumOfTrack' => isset($sampleTrack['albumOfTrack']),
                'has_album' => isset($sampleTrack['album']),
                'has_popularity' => isset($sampleTrack['popularity']),
                'popularity_value' => $sampleTrack['popularity'] ?? 'not_set',
                'album_release_date' => $sampleTrack['album']['release_date'] ?? 'not_set',
                'albumOfTrack_keys' => array_keys($albumOfTrack),
                'albumOfTrack_date' => $albumOfTrack['date'] ?? 'not_set',
                'albumOfTrack_date_type' => isset($albumOfTrack['date']) ? gettype($albumOfTrack['date']) : 'not_set',
                'albumOfTrack_date_value' => isset($albumOfTrack['date']) ? (is_array($albumOfTrack['date']) ? json_encode($albumOfTrack['date']) : $albumOfTrack['date']) : 'not_set',
                'track_keys' => array_keys($sampleTrack),
            ]);
        }
        
        // Note: Popularity is not available in RapidAPI search results
        // Fetching it would require 100+ individual API calls which is too slow
        // We'll set popularity to 0 and let the user filter by other criteria

        return array_map(function ($trackWrapper) use ($albumDetailsMap, $searchYear) {
            // Handle both structures: RapidAPI wraps tracks in 'data', official Spotify doesn't
            $track = $trackWrapper['data'] ?? $trackWrapper;

            // Detect structure: RapidAPI has nested artists.items, Official has direct artists array
            // Also check if track has albumOfTrack (RapidAPI) vs album (Official)
            $isRapidApiStructure = isset($track['artists']['items']) || isset($track['albumOfTrack']);

            // Extract artist info based on structure
            $primaryArtist = [
                'name' => 'Unknown Artist',
                'id' => null,
                'external_urls' => ['spotify' => null]
            ];
            $artists = [];
            
            if ($isRapidApiStructure) {
                // RapidAPI structure: artists.items[].profile.name
                $artistItems = $track['artists']['items'] ?? [];
                if (!empty($artistItems)) {
                    $primaryArtist = [
                        'name' => $artistItems[0]['profile']['name'] ?? 'Unknown Artist',
                        'id' => str_replace('spotify:artist:', '', $artistItems[0]['uri'] ?? ''),
                        'external_urls' => [
                            'spotify' => $artistItems[0]['uri'] ? str_replace('spotify:artist:', 'https://open.spotify.com/artist/', $artistItems[0]['uri']) : null
                        ]
                    ];
                }
                $artists = array_map(function($artist) {
                    return [
                        'name' => $artist['profile']['name'] ?? 'Unknown',
                        'id' => str_replace('spotify:artist:', '', $artist['uri'] ?? ''),
                    ];
                }, $artistItems);
            } else {
                // Official Spotify structure: artists[].name
                $artistArray = $track['artists'] ?? [];
                if (!empty($artistArray)) {
                    $primaryArtist = $artistArray[0] ?? $primaryArtist;
                }
                $artists = array_map(function($artist) {
                    return [
                        'name' => $artist['name'] ?? 'Unknown',
                        'id' => $artist['id'] ?? null,
                    ];
                }, $artistArray);
            }

            // Extract album info based on structure
            $album = [];
            $releaseDate = null;
            
            if ($isRapidApiStructure) {
                // RapidAPI structure: albumOfTrack
                $albumOfTrack = $track['albumOfTrack'] ?? [];
                $album = [
                    'id' => str_replace('spotify:album:', '', $albumOfTrack['uri'] ?? ''),
                    'name' => $albumOfTrack['name'] ?? 'Unknown Album',
                    'images' => [
                        [
                            'url' => $albumOfTrack['coverArt']['sources'][0]['url'] ?? null
                        ]
                    ],
                    'release_date' => null, // Will be set below
                    'external_urls' => [
                        'spotify' => $albumOfTrack['uri'] ? str_replace('spotify:album:', 'https://open.spotify.com/album/', $albumOfTrack['uri']) : null
                    ]
                ];
                
                // Get release date and popularity from fetched album details
                $albumId = str_replace('spotify:album:', '', $albumOfTrack['uri'] ?? '');
                $releaseDate = null;
                
                // Get from fetched album details
                if (!empty($albumId) && isset($albumDetailsMap[$albumId])) {
                    $releaseDate = $albumDetailsMap[$albumId]['release_date'] ?? null;
                }
                
                // If not found, try to extract from albumOfTrack (though it's usually not there)
                if (!$releaseDate) {
                    $dateFields = ['date', 'releaseDate', 'release_date', 'releaseDatePrecision'];
                    foreach ($dateFields as $field) {
                        if (isset($albumOfTrack[$field])) {
                            $dateValue = $albumOfTrack[$field];
                            
                            if (is_array($dateValue)) {
                                $year = $dateValue['year'] ?? null;
                                $month = $dateValue['month'] ?? null;
                                $day = $dateValue['day'] ?? null;
                                if ($year) {
                                    if ($month && $day) {
                                        $releaseDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    } elseif ($month) {
                                        $releaseDate = sprintf('%04d-%02d', $year, $month);
                                    } else {
                                        $releaseDate = (string) $year;
                                    }
                                    break;
                                }
                            } elseif (is_string($dateValue) && !empty($dateValue)) {
                                $releaseDate = $dateValue;
                                break;
                            } elseif (is_numeric($dateValue)) {
                                $releaseDate = (string) $dateValue;
                                break;
                            }
                        }
                    }
                }
                
                $album['release_date'] = $releaseDate;
            } else {
                // Official Spotify structure: album
                $album = $track['album'] ?? [];
                // Release date can be in format "YYYY", "YYYY-MM", or "YYYY-MM-DD"
                $releaseDate = $album['release_date'] ?? null;
                
                // If release_date is not set, try alternative locations
                if (!$releaseDate && isset($album['release_date_precision'])) {
                    // Sometimes the date is split into components
                    $releaseDate = null;
                }
            }

            // Extract popularity - try multiple sources
            $popularity = 0;
            
            // First, try from track itself (if available)
            if (isset($track['popularity'])) {
                $popularity = (int) $track['popularity'];
            } 
            // Second, try from album details (album popularity, not track)
            else {
                // Get album ID - MUST use the same logic as when collecting IDs
                // We collect using: albumOfTrack['id'] OR extract from uri
                $albumIdForLookup = null;
                if ($isRapidApiStructure) {
                    // Use the SAME logic as collection: try 'id' first, then extract from 'uri'
                    $albumIdForLookup = $albumOfTrack['id'] ?? null;
                    if (!$albumIdForLookup && !empty($albumOfTrack['uri'])) {
                        $albumIdForLookup = str_replace('spotify:album:', '', $albumOfTrack['uri']);
                    }
                } else {
                    $albumIdForLookup = $album['id'] ?? null;
                }
                
                // Look up in album details map
                if (!empty($albumIdForLookup) && isset($albumDetailsMap[$albumIdForLookup])) {
                    $popularity = (int) ($albumDetailsMap[$albumIdForLookup]['popularity'] ?? 0);
                }
            }

            return [
                'spotify_id' => $track['id'] ?? null,
                'album_id' => $album['id'] ?? null,
                'isrc' => $track['external_ids']['isrc'] ?? null,
                'track_name' => $track['name'] ?? 'Unknown Track',
                'artist_name' => $primaryArtist['name'] ?? 'Unknown Artist',
                'artist_id' => $primaryArtist['id'] ?? null,
                'spotify_artist_id' => $primaryArtist['id'] ?? null,
                'album_name' => $album['name'] ?? 'Unknown Album',
                'album_cover' => $album['images'][0]['url'] ?? null,
                'popularity' => (int) $popularity,
                'release_date' => $releaseDate,
                'preview_url' => $track['preview_url'] ?? $track['previewUrl'] ?? null,
                'spotify_track_url' => $track['external_urls']['spotify'] ?? null,
                'spotify_album_url' => $album['external_urls']['spotify'] ?? null,
                'spotify_artist_url' => $primaryArtist['external_urls']['spotify'] ?? null,
                'artists' => $artists,
            ];
        }, $tracks);
    }

    private function filterBannedTracksAndArtists(array $tracks): array
    {
        $userId = auth()->id();
        if (!$userId) {
            return $tracks;
        }

        // Get blacklisted ISRCs and artist IDs
        $blacklistedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);
        $blacklistedArtistIds = BlacklistedArtist::getBlacklistedArtistIds($userId);
        
        // Also get blacklisted artist names for broader filtering
        $blacklistedArtistNames = [];
        try {
            $blacklistedArtists = BlacklistedArtist::where('user_id', $userId)->get();
            foreach ($blacklistedArtists as $artist) {
                $blacklistedArtistNames[] = strtolower(trim($artist->artist_name ?? ''));
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch blacklisted artist names: ' . $e->getMessage());
        }

        return array_filter($tracks, function($track) use ($blacklistedIsrcs, $blacklistedArtistIds, $blacklistedArtistNames) {
            // Filter out blacklisted tracks by ISRC
            $isrc = $track['isrc'] ?? null;
            if ($isrc && in_array($isrc, $blacklistedIsrcs)) {
                return false;
            }

            // Filter out tracks by blacklisted artists (by ID)
            $artistId = $track['artist_id'] ?? null;
            if ($artistId && in_array($artistId, $blacklistedArtistIds)) {
                return false;
            }

            // Filter out tracks by blacklisted artists (by name)
            $artistName = strtolower(trim($track['artist_name'] ?? ''));
            if ($artistName && in_array($artistName, $blacklistedArtistNames)) {
                return false;
            }

            return true;
        });
    }

    private function addUserPreferenceStatus(array $tracks): array
    {
        $userId = auth()->id();

        if (!$userId) {
            return array_map(fn($track) => array_merge($track, [
                'is_saved' => false,
                'is_banned' => false,
                'is_artist_banned' => false,
            ]), $tracks);
        }
        $savedIsrcs = SavedTrack::getSavedIsrcs($userId);
        $bannedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);
        $blacklistedArtistIds = BlacklistedArtist::getBlacklistedArtistIds($userId);

        return array_map(function ($track) use ($savedIsrcs, $bannedIsrcs, $blacklistedArtistIds) {
            $isrc = $track['isrc'] ?? null;
            $artistId = $track['artist_id'] ?? null;

            return array_merge($track, [
                'is_saved' => $isrc && in_array($isrc, $savedIsrcs),
                'is_banned' => $isrc && in_array($isrc, $bannedIsrcs),
                'is_artist_banned' => $artistId && in_array($artistId, $blacklistedArtistIds),
            ]);
        }, $tracks);
    }
}

