<?php

/**
 * SoundCloud API Integration Service
 * 
 * Handles SoundCloud API requests with OAuth authentication, search functionality,
 * and embed URL generation for track players. Provides advanced filtering capabilities
 * including genre, BPM, duration, and popularity filters.
 * 
 * @package App\Services
 * @author Koel Development Team
 * @version 1.0.0
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SoundCloudService
{
    /**
     * SoundCloud API base configuration
     */
    private const API_BASE_URL = 'https://api.soundcloud.com';
    private const OAUTH_TOKEN_URL = 'https://api.soundcloud.com/oauth2/token';
    private const EMBED_PLAYER_URL = 'https://w.soundcloud.com/player/';
    private const TOKEN_CACHE_KEY = 'soundcloud_access_token';
    private const TOKEN_TTL_MINUTES = 50;

    public function __construct()
    {
        \Log::info('🎵 SoundCloud Service initialized', [
            'client_id_present' => !empty(config('services.soundcloud.client_id')),
            'client_secret_present' => !empty(config('services.soundcloud.client_secret')),
        ]);
    }

    /**
     * Check if SoundCloud integration is properly configured
     * 
     * @return bool True if client credentials are configured
     */
    public static function enabled(): bool
    {
        return (bool) config('services.soundcloud.client_id') && 
               (bool) config('services.soundcloud.client_secret');
    }

    /**
     * Search SoundCloud tracks with advanced filtering
     * 
     * Searches the SoundCloud API with various filter parameters including genre,
     * BPM range, duration, date range, and text queries. Uses OAuth2 authentication
     * and implements caching for improved performance.
     * 
     * @param array $params Search parameters (q, genre, bpm[from], bpm[to], etc.)
     * @return object|null Search results object with collection of tracks, null on failure
     */
    public function searchTracks(array $params = []): ?object
    {
        \Log::info('🔍 SoundCloud track search initiated', [
            'enabled' => self::enabled(),
            'params' => array_keys($params),
            'timestamp' => now()->toISOString()
        ]);

        if (!self::enabled()) {
            \Log::warning('❌ SoundCloud integration not enabled - missing credentials');
            return null;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            \Log::error('❌ Failed to obtain SoundCloud access token');
            return null;
        }

        $searchParams = array_merge([
            'limit' => 20,
            'access' => 'playable', // Only get fully playable tracks, exclude previews
            'linked_partitioning' => true,
        ], $params);

        // Add cache-busting parameters to ensure fresh results for each search
        // This prevents SoundCloud from returning cached results that are identical across searches
        $searchParams['_timestamp'] = now()->timestamp * 1000; // Milliseconds like your Python script
        
        // Only set offset to 0 if not already provided (for pagination)
        if (!isset($searchParams['offset'])) {
            $searchParams['offset'] = 0;
        }
        
        // Add random seed to prevent result caching issues
        $searchParams['_rand'] = mt_rand(1000, 9999);

        try {
            \Log::info('📋 SoundCloud search parameters prepared', [
                'url' => self::API_BASE_URL . '/tracks',
                'params' => $searchParams,
                'cache_busting' => 'Added timestamp and offset for fresh results'
            ]);

            // IMPORTANT: Disable Laravel caching temporarily to debug API issues
            // return $this->performSearch($searchParams, $accessToken);
            return $this->performSearch($searchParams, $accessToken);
        } catch (Throwable $e) {
            \Log::error('❌ SoundCloud search failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Perform the actual HTTP request to SoundCloud API
     * 
     * Makes authenticated HTTP request to SoundCloud tracks endpoint with
     * proper error handling and response format standardization.
     * 
     * @param array $params Search parameters
     * @param string $accessToken OAuth2 access token
     * @return object|null Standardized response object or null on failure
     */
    private function performSearch(array $params, string $accessToken): ?object
    {
        \Log::info('🌐 Making HTTP request to SoundCloud API', [
            'method' => 'GET',
            'url' => self::API_BASE_URL . '/tracks',
            'params' => $params,
            'headers' => [
                'Authorization' => 'Bearer ' . substr($accessToken, 0, 20) . '...',
                'Accept' => 'application/json'
            ],
            'timestamp' => now()->toISOString()
        ]);

        // Build complete URL for logging
        $fullUrl = self::API_BASE_URL . '/tracks?' . http_build_query($params);
        
        \Log::info('🌐 Making SoundCloud API request exactly like Python script', [
            'full_url' => $fullUrl,
            'genre_requested' => $params['genres'] ?? 'none',
            'limit' => $params['limit'] ?? 'none',
            'timestamp' => $params['_timestamp'] ?? 'none',
            'method' => 'GET',
            'curl_equivalent' => 'curl -H "Authorization: Bearer [TOKEN]" "' . $fullUrl . '"'
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'User-Agent' => 'Koel/' . config('app.version', '1.0.0') . ' (Music Streaming Application)'
        ])->timeout(30)->get(self::API_BASE_URL . '/tracks', $params);

        \Log::info('📊 SoundCloud API HTTP Response', [
            'status_code' => $response->status(),
            'response_size_bytes' => strlen($response->body()),
            'content_type' => $response->header('Content-Type'),
            'rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
            'response_preview' => substr($response->body(), 0, 100)
        ]);

        if ($response->successful()) {
            $data = $response->object();
            
            // Handle different SoundCloud API response formats
            if (is_array($data)) {
                \Log::info('✅ SoundCloud Response Format: Direct Array', [
                    'track_count' => count($data),
                    'first_track_title' => $data[0]->title ?? 'N/A',
                    'first_track_genre' => $data[0]->genre ?? 'N/A',
                    'first_track_id' => $data[0]->id ?? 'N/A',
                    'all_track_titles' => array_slice(array_map(function($track) {
                        return $track->title ?? 'N/A';
                    }, $data), 0, 5)
                ]);
                
                // DISABLED: Smart filtering causes more problems than it solves
                // Just return what SoundCloud gives us, like the Python script
                \Log::info('🎵 Returning raw SoundCloud results without filtering');
                return (object) ['collection' => $data];
            } elseif (isset($data->collection)) {
                \Log::info('✅ SoundCloud Response Format: Collection Object', [
                    'track_count' => count($data->collection),
                    'first_track_title' => $data->collection[0]->title ?? 'N/A',
                    'first_track_genre' => $data->collection[0]->genre ?? 'N/A',
                    'first_track_id' => $data->collection[0]->id ?? 'N/A',
                    'next_href' => $data->next_href ?? null,
                    'all_track_titles' => array_slice(array_map(function($track) {
                        return $track->title ?? 'N/A';
                    }, $data->collection), 0, 5)
                ]);
                
                // DISABLED: Smart filtering causes more problems than it solves
                // Just return what SoundCloud gives us, like the Python script
                \Log::info('🎵 Returning raw SoundCloud results without filtering');
                return $data;
            }
            
            \Log::warning('⚠️ SoundCloud Response Format: Unknown structure', [
                'data_type' => gettype($data),
                'data_keys' => is_object($data) ? array_keys((array)$data) : 'N/A'
            ]);
            return (object) ['collection' => []];
        }

        \Log::error('❌ SoundCloud API HTTP Error', [
            'status_code' => $response->status(),
            'error_body' => $response->body(),
            'headers' => $response->headers()
        ]);

        return null;
    }

    /**
     * Get OAuth2 access token using client credentials flow
     * 
     * Implements OAuth2 client credentials grant to obtain access token for
     * SoundCloud API requests. Uses Laravel cache for token persistence.
     * 
     * @return string|null Access token or null on authentication failure
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(self::TOKEN_TTL_MINUTES), function () {
            \Log::info('🔑 Authenticating with SoundCloud OAuth2', [
                'grant_type' => 'client_credentials',
                'url' => self::OAUTH_TOKEN_URL,
                'client_id' => substr(config('services.soundcloud.client_id'), 0, 10) . '...',
                'timestamp' => now()->toISOString()
            ]);

            try {
                $response = Http::asForm()
                    ->timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Koel/' . config('app.version', '1.0.0') . ' (Music Streaming Application)',
                        'Accept' => 'application/json'
                    ])
                    ->post(self::OAUTH_TOKEN_URL, [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('services.soundcloud.client_id'),
                        'client_secret' => config('services.soundcloud.client_secret'),
                    ]);

                \Log::info('📊 SoundCloud OAuth2 Response', [
                    'status_code' => $response->status(),
                    'response_size_bytes' => strlen($response->body()),
                    'content_type' => $response->header('Content-Type')
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $accessToken = $data['access_token'] ?? null;
                    
                    if ($accessToken) {
                        \Log::info('✅ SoundCloud OAuth2 authentication successful', [
                            'token_type' => $data['token_type'] ?? 'Bearer',
                            'expires_in' => $data['expires_in'] ?? 'Unknown',
                            'token_preview' => substr($accessToken, 0, 20) . '...'
                        ]);
                        return $accessToken;
                    }
                }

                \Log::error('❌ SoundCloud OAuth2 authentication failed', [
                    'status_code' => $response->status(),
                    'error_body' => $response->body()
                ]);

                return null;
            } catch (Throwable $e) {
                \Log::error('❌ SoundCloud OAuth2 request exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return null;
            }
        });
    }

    /**
     * Generate SoundCloud HTML5 embed URL for track player
     * 
     * Creates a properly formatted embed URL for SoundCloud's HTML5 player widget
     * with customizable player options and styling parameters.
     * 
     * Documentation: https://help.soundcloud.com/hc/en-us/articles/115003449627-The-HTML5-embedded-player
     * 
     * @param string $trackId SoundCloud track ID
     * @param array $options Player configuration options
     * @return string Complete embed URL for iframe src
     */
    public function generateEmbedUrl(string $trackId, array $options = []): string
    {
        \Log::info('🎵 Generating SoundCloud embed URL', [
            'track_id' => $trackId,
            'options' => $options,
            'timestamp' => now()->toISOString()
        ]);

        $defaultOptions = [
            'auto_play' => false,
            'hide_related' => false,
            'show_comments' => true,
            'show_user' => true,
            'show_reposts' => false,
            'visual' => true,
            'color' => 'ff5500', // SoundCloud orange
        ];

        $config = array_merge($defaultOptions, $options);

        $params = [
            'url' => self::API_BASE_URL . "/tracks/{$trackId}",
            'color' => $config['color'],
            'auto_play' => $config['auto_play'] ? 'true' : 'false',
            'hide_related' => $config['hide_related'] ? 'true' : 'false',
            'show_comments' => $config['show_comments'] ? 'true' : 'false',
            'show_user' => $config['show_user'] ? 'true' : 'false',
            'show_reposts' => $config['show_reposts'] ? 'true' : 'false',
            'visual' => $config['visual'] ? 'true' : 'false',
        ];

        $queryString = http_build_query($params);
        $embedUrl = self::EMBED_PLAYER_URL . "?{$queryString}";
        
        \Log::info('✅ SoundCloud embed URL generated', [
            'embed_url' => $embedUrl,
            'params_count' => count($params)
        ]);

        return $embedUrl;
    }

    /**
     * Get related tracks for a SoundCloud track using track URN
     * 
     * @param string $trackUrn SoundCloud track URN (e.g., soundcloud:tracks:123456)
     * @return object|null Related tracks collection or null on failure
     */
    public function getRelatedTracks(string $trackUrn): ?object
    {
        \Log::info('🔥🔥🔥 SOUNDCLOUD SERVICE - FETCHING RELATED TRACKS!!! 🔥🔥🔥', [
            'track_urn' => $trackUrn,
            'timestamp' => now()->toISOString(),
            'service_enabled' => self::enabled(),
            'has_client_id' => !empty(config('services.soundcloud.client_id')),
            'has_client_secret' => !empty(config('services.soundcloud.client_secret'))
        ]);

        if (!self::enabled()) {
            \Log::warning('❌ SoundCloud integration not enabled - missing credentials');
            return null;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            \Log::error('❌ Failed to obtain SoundCloud access token');
            return null;
        }

        try {
            // The related tracks endpoint format: /tracks/{track_urn}/related
            $endpoint = "/tracks/{$trackUrn}/related";
            $params = [
                'limit' => 20,  // Reasonable limit for related tracks
                'access' => 'playable', // Only get fully playable tracks, exclude previews
                'linked_partitioning' => true,
            ];

            \Log::info('🌐 Making HTTP request to SoundCloud related tracks API', [
                'method' => 'GET',
                'url' => self::API_BASE_URL . $endpoint,
                'params' => $params,
                'track_urn' => $trackUrn,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'User-Agent' => 'Koel/' . config('app.version', '1.0.0') . ' (Music Streaming Application)'
            ])->timeout(30)->get(self::API_BASE_URL . $endpoint, $params);

            \Log::info('📊 SoundCloud Related Tracks API HTTP Response', [
                'status_code' => $response->status(),
                'response_size_bytes' => strlen($response->body()),
                'content_type' => $response->header('Content-Type'),
                'rate_limit_remaining' => $response->header('X-RateLimit-Remaining')
            ]);

            if ($response->successful()) {
                $data = $response->object();
                
                // Handle different SoundCloud API response formats
                if (is_array($data)) {
                    \Log::info('✅ SoundCloud Related Tracks Response Format: Direct Array', [
                        'track_count' => count($data),
                        'first_track_title' => $data[0]->title ?? 'N/A'
                    ]);
                    
                    return (object) ['collection' => $data];
                } elseif (isset($data->collection)) {
                    \Log::info('✅ SoundCloud Related Tracks Response Format: Collection Object', [
                        'track_count' => count($data->collection),
                        'first_track_title' => $data->collection[0]->title ?? 'N/A',
                        'next_href' => $data->next_href ?? null
                    ]);
                    
                    return $data;
                }
                
                \Log::warning('⚠️ SoundCloud Related Tracks Response Format: Unknown structure', [
                    'data_type' => gettype($data),
                    'data_keys' => is_object($data) ? array_keys((array)$data) : 'N/A'
                ]);
                return (object) ['collection' => []];
            }

            \Log::error('❌ SoundCloud Related Tracks API HTTP Error', [
                'status_code' => $response->status(),
                'error_body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return null;
        } catch (Throwable $e) {
            \Log::error('❌ SoundCloud related tracks request exception', [
                'track_urn' => $trackUrn,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get SoundCloud user details using the /users/{user_id} endpoint
     * 
     * @param int $userId SoundCloud user ID
     * @return object|null User details object or null on failure
     */
    public function getUserDetails(int $userId): ?object
    {
        \Log::info('🎵 Fetching SoundCloud user details', [
            'user_id' => $userId,
            'timestamp' => now()->toISOString()
        ]);

        if (!self::enabled()) {
            \Log::warning('❌ SoundCloud integration not enabled - missing credentials');
            return null;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            \Log::error('❌ Failed to obtain SoundCloud access token');
            return null;
        }

        try {
            \Log::info('🌐 Making HTTP request to SoundCloud users API', [
                'method' => 'GET',
                'url' => self::API_BASE_URL . "/users/{$userId}",
                'user_id' => $userId,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'User-Agent' => 'Koel/' . config('app.version', '1.0.0') . ' (Music Streaming Application)'
            ])->timeout(30)->get(self::API_BASE_URL . "/users/{$userId}");

            \Log::info('📊 SoundCloud Users API HTTP Response', [
                'status_code' => $response->status(),
                'response_size_bytes' => strlen($response->body()),
                'content_type' => $response->header('Content-Type'),
                'rate_limit_remaining' => $response->header('X-RateLimit-Remaining')
            ]);

            if ($response->successful()) {
                $data = $response->object();
                
                \Log::info('✅ SoundCloud user details fetched successfully', [
                    'user_id' => $userId,
                    'username' => $data->username ?? 'Unknown',
                    'followers_count' => $data->followers_count ?? 0,
                    'followings_count' => $data->followings_count ?? 0,
                    'track_count' => $data->track_count ?? 0
                ]);
                
                return $data;
            }

            \Log::error('❌ SoundCloud Users API HTTP Error', [
                'status_code' => $response->status(),
                'error_body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return null;
        } catch (Throwable $e) {
            \Log::error('❌ SoundCloud user details request exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Apply smart filtering to improve SoundCloud result accuracy
     * 
     * SoundCloud's genre tagging is notoriously inaccurate. This method applies
     * intelligent filtering based on track metadata, artist names, and content
     * analysis to remove obviously misclassified tracks.
     * 
     * @param array $tracks Raw track collection from SoundCloud API
     * @param array $searchParams Original search parameters for context
     * @return array Filtered track collection with improved accuracy
     */
    private function applySmartFiltering(array $tracks, array $searchParams): array
    {
        if (empty($tracks)) {
            return $tracks;
        }

        $originalCount = count($tracks);
        $originalTracks = $tracks; // Store original tracks for fallback
        $requestedGenre = $searchParams['genres'] ?? null;

        \Log::info('🧠 Applying smart filtering to SoundCloud results', [
            'original_count' => $originalCount,
            'requested_genre' => $requestedGenre,
            'first_track_title' => $tracks[0]->title ?? 'N/A'
        ]);

        // Apply genre-specific filtering (only if genre is very specific)
        if ($requestedGenre && $this->shouldApplyGenreFiltering($requestedGenre)) {
            $tracks = $this->applyGenreSpecificFiltering($tracks, $requestedGenre);
        } else {
            \Log::info('🧠 Skipping genre filtering for broad genre', [
                'requested_genre' => $requestedGenre,
                'reason' => 'Letting SoundCloud API handle genre filtering'
            ]);
        }

        // Apply minimal quality filters only
        $tracks = $this->applyGeneralQualityFilters($tracks);

        $filteredCount = count($tracks);
        $filterEffectiveness = $originalCount > 0 ? (($originalCount - $filteredCount) / $originalCount) * 100 : 0;

        // IMPORTANT: If filtering removed ALL tracks, return original results as fallback
        // This prevents the search from returning zero results due to overly aggressive filtering
        if ($filteredCount === 0 && $originalCount > 0) {
            \Log::warning('⚠️ Smart filtering removed ALL tracks - using fallback to original results', [
                'original_count' => $originalCount,
                'requested_genre' => $requestedGenre,
                'fallback_reason' => 'Preventing zero results from overly aggressive filtering'
            ]);
            
            // Return original tracks but still apply basic quality filters only
            $fallbackTracks = $this->applyGeneralQualityFilters($originalTracks);
            
            \Log::info('✅ Fallback filtering completed', [
                'original_count' => $originalCount,
                'fallback_count' => count($fallbackTracks),
                'first_track' => $fallbackTracks[0]->title ?? 'N/A'
            ]);
            
            return $fallbackTracks;
        }

        \Log::info('✅ Smart filtering completed', [
            'original_count' => $originalCount,
            'filtered_count' => $filteredCount,
            'removed_count' => $originalCount - $filteredCount,
            'filter_effectiveness' => round($filterEffectiveness, 1) . '%',
            'first_remaining_track' => $tracks[0]->title ?? 'N/A'
        ]);

        return $tracks;
    }

    /**
     * Determine if we should apply additional genre filtering
     * Only apply for genres that are commonly mis-tagged on SoundCloud
     * 
     * @param string $requestedGenre The genre being searched for
     * @return bool Whether to apply additional filtering
     */
    private function shouldApplyGenreFiltering(string $requestedGenre): bool
    {
        $problematicGenres = [
            'classical',
            'country',
            'folk & singer-songwriter',
            'jazz & blues'
        ];
        
        return in_array(strtolower($requestedGenre), $problematicGenres);
    }

    /**
     * Apply genre-specific filtering rules
     * 
     * @param array $tracks Track collection
     * @param string $requestedGenre The genre being searched for
     * @return array Filtered tracks
     */
    private function applyGenreSpecificFiltering(array $tracks, string $requestedGenre): array
    {
        switch (strtolower($requestedGenre)) {
            case 'classical':
                return $this->filterClassicalMusic($tracks);
            case 'country':
                return $this->filterCountryMusic($tracks);
            case 'rock':
            case 'alternative rock':
                return $this->filterRockMusic($tracks);
            case 'electronic':
            case 'dance & edm':
            case 'dubstep':
            case 'house':
            case 'techno':
            case 'trance':
            case 'drum & bass':
                return $this->filterElectronicMusic($tracks);
            default:
                return $tracks;
        }
    }

    /**
     * Filter tracks to ensure they are actually Classical music
     * 
     * @param array $tracks Track collection
     * @return array Filtered classical tracks
     */
    private function filterClassicalMusic(array $tracks): array
    {
        $classicalKeywords = [
            'classical', 'symphony', 'concerto', 'sonata', 'opera', 'ballet',
            'orchestra', 'philharmonic', 'chamber', 'quartet', 'piano solo',
            'piano', 'violin', 'cello', 'flute', 'harp', 'strings',
            'bach', 'mozart', 'beethoven', 'chopin', 'vivaldi', 'brahms', 
            'tchaikovsky', 'debussy', 'liszt', 'schubert', 'handel', 'pachelbel',
            'instrumental', 'orchestral', 'classical music', 'baroque', 'romantic'
        ];

        $excludeKeywords = [
            'محكمه', 'مهرجان', 'عصام', 'توزيع', 'كليب', 'حفلة', 'أغنية',
            'trap', 'drill', 'phonk', 'remix', 'beat', 'dj', 'mc'
        ];

        return array_filter($tracks, function($track) use ($classicalKeywords, $excludeKeywords) {
            $searchText = strtolower($track->title . ' ' . ($track->user->username ?? '') . ' ' . ($track->genre ?? '') . ' ' . ($track->tag_list ?? ''));
            
            // Exclude obviously non-classical content
            foreach ($excludeKeywords as $keyword) {
                if (strpos($searchText, strtolower($keyword)) !== false) {
                    \Log::debug('🚫 Filtering out non-classical track', [
                        'title' => $track->title,
                        'reason' => "Contains excluded keyword: {$keyword}"
                    ]);
                    return false;
                }
            }
            
            // Check for classical indicators
            foreach ($classicalKeywords as $keyword) {
                if (strpos($searchText, $keyword) !== false) {
                    \Log::debug('✅ Keeping classical track', [
                        'title' => $track->title,
                        'reason' => "Contains classical keyword: {$keyword}"
                    ]);
                    return true;
                }
            }
            
            // If no clear indicators but also no exclusion keywords, keep it (less aggressive filtering)
            // This handles cases where tracks might be classical but don't match our keywords exactly
            if ($track->genre && stripos($track->genre, 'classical') !== false) {
                \Log::debug('✅ Keeping track with classical genre tag', [
                    'title' => $track->title,
                    'genre' => $track->genre,
                    'reason' => 'Has classical in genre despite no keyword match'
                ]);
                return true;
            }
            
            // If no clear indicators, be conservative and exclude
            \Log::debug('❓ Filtering out ambiguous track', [
                'title' => $track->title,
                'reason' => 'No clear classical indicators found'
            ]);
            return false;
        });
    }

    /**
     * Filter tracks to ensure they are actually Country music
     * LESS AGGRESSIVE - Only exclude obvious non-country content
     * 
     * @param array $tracks Track collection
     * @return array Filtered country tracks
     */
    private function filterCountryMusic(array $tracks): array
    {
        $excludeKeywords = [
            'محكمه', 'مهرجان', 'عصام', 'توزيع', 'كليب', 'حفلة', 'أغنية',
            'trap', 'drill', 'phonk', 'techno', 'house', 'dubstep', 'hardstyle'
        ];

        return array_filter($tracks, function($track) use ($excludeKeywords) {
            $searchText = strtolower($track->title . ' ' . ($track->user->username ?? '') . ' ' . ($track->genre ?? '') . ' ' . ($track->tag_list ?? ''));
            
            // Only exclude obviously non-country content (Arabic and electronic)
            foreach ($excludeKeywords as $keyword) {
                if (strpos($searchText, strtolower($keyword)) !== false) {
                    \Log::debug('🚫 Filtering out non-country track', [
                        'title' => $track->title,
                        'reason' => "Contains excluded keyword: {$keyword}"
                    ]);
                    return false;
                }
            }
            
            // Be very lenient - if it's tagged as Country by SoundCloud, trust it
            \Log::debug('✅ Keeping potential country track', [
                'title' => $track->title,
                'genre' => $track->genre ?? 'N/A',
                'reason' => 'No exclusion keywords found'
            ]);
            return true;
        });
    }

    /**
     * Filter tracks for Rock music
     * 
     * @param array $tracks Track collection
     * @return array Filtered rock tracks
     */
    private function filterRockMusic(array $tracks): array
    {
        $rockKeywords = [
            'rock', 'metal', 'punk', 'grunge', 'alternative', 'indie rock',
            'hard rock', 'guitar', 'drums', 'bass', 'band', 'concert',
            'live', 'electric', 'distortion', 'riff'
        ];

        $excludeKeywords = [
            'محكمه', 'مهرجان', 'عصام', 'توزيع', 'كليب', 'حفلة', 'أغنية'
        ];

        return array_filter($tracks, function($track) use ($rockKeywords, $excludeKeywords) {
            $searchText = strtolower($track->title . ' ' . ($track->user->username ?? '') . ' ' . ($track->genre ?? '') . ' ' . ($track->tag_list ?? ''));
            
            // Exclude obviously non-rock content
            foreach ($excludeKeywords as $keyword) {
                if (strpos($searchText, strtolower($keyword)) !== false) {
                    return false;
                }
            }
            
            // More lenient for rock as it's a broad category
            foreach ($rockKeywords as $keyword) {
                if (strpos($searchText, $keyword) !== false) {
                    return true;
                }
            }
            
            return true; // Default to keeping if no exclusion keywords found
        });
    }

    /**
     * Filter tracks for Electronic/EDM music
     * 
     * Electronic music filtering is very lenient since these genres are well-defined
     * and SoundCloud's electronic genre tagging is generally more accurate than classical.
     * 
     * @param array $tracks Track collection
     * @return array Filtered electronic tracks
     */
    private function filterElectronicMusic(array $tracks): array
    {
        // Only exclude obvious Arabic non-electronic content, keep everything else
        $excludeKeywords = [
            'محكمه', 'مهرجان', 'عصام', 'توزيع', 'كليب', 'حفلة', 'أغنية'
        ];

        return array_filter($tracks, function($track) use ($excludeKeywords) {
            $searchText = strtolower($track->title . ' ' . ($track->user->username ?? '') . ' ' . ($track->genre ?? '') . ' ' . ($track->tag_list ?? ''));
            
            // Only exclude obviously non-electronic Arabic content
            foreach ($excludeKeywords as $keyword) {
                if (strpos($searchText, strtolower($keyword)) !== false) {
                    \Log::debug('🚫 Filtering out non-electronic track', [
                        'title' => $track->title,
                        'reason' => "Contains excluded Arabic keyword: {$keyword}"
                    ]);
                    return false;
                }
            }
            
            // Keep everything else - electronic genres are generally well-tagged on SoundCloud
            \Log::debug('✅ Keeping electronic track', [
                'title' => $track->title,
                'genre' => $track->genre ?? 'N/A',
                'reason' => 'Electronic genres are generally well-tagged'
            ]);
            return true;
        });
    }

    /**
     * Apply general quality filters to remove low-quality or spam content
     * 
     * These filters are kept minimal to avoid overly aggressive filtering.
     * Only the most obvious spam/bot content is removed.
     * 
     * @param array $tracks Track collection
     * @return array Filtered tracks
     */
    private function applyGeneralQualityFilters(array $tracks): array
    {
        return array_filter($tracks, function($track) {
            // Filter out tracks with suspicious characteristics
            $title = $track->title ?? '';
            $username = $track->user->username ?? '';
            
            // Only remove very obviously problematic content
            
            // Remove completely empty titles
            if (empty(trim($title))) {
                \Log::debug('🚫 Filtering out track with empty title');
                return false;
            }
            
            // Remove tracks from obviously spam users (very restrictive pattern)
            if (preg_match('/^(bot|spam|fake|test)\d*$/i', $username)) {
                \Log::debug('🚫 Filtering out track from obvious spam user', [
                    'username' => $username,
                    'title' => $title
                ]);
                return false;
            }
            
            // Keep everything else - be very lenient with general quality filtering
            return true;
        });
    }
}