<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RapidAPI Spotify Service with Primary/Backup Fallback
 * Primary: spotify81.p.rapidapi.com (5 req/sec)
 * Backup: spotify-web2.p.rapidapi.com (5 req/sec)
 */
class RapidApiSpotifyService
{
    private string $primaryHost;
    private string $backupHost;
    private string $tertiaryHost;
    private string $apiKey;
    private string $backupApiKey;
    private string $tertiaryApiKey;
    private float $rateLimitDelay = 0.2; // 5 req/sec = 200ms delay
    private int $providerRateLimitPerSecond = 5; // RapidAPI free tier per host
    private int $rateLimitWindowSeconds = 1;
    private int $rateLimitGraceMs = 150; // optional short wait to next window

    public function __construct()
    {
        // Primary provider: spotify81
        $this->primaryHost = config('services.rapidapi_spotify.primary_host', 'spotify81.p.rapidapi.com');
        $this->apiKey = config('services.rapidapi_spotify.primary_key', config('services.rapidapi_spotify.key'));

        // Backup provider: spotify-web2
        $this->backupHost = config('services.rapidapi_spotify.backup_host', 'spotify-web2.p.rapidapi.com');
        $this->backupApiKey = config('services.rapidapi_spotify.backup_key', $this->apiKey);

        // Tertiary provider: spotify23
        $this->tertiaryHost = config('services.rapidapi_spotify.tertiary_host', 'spotify23.p.rapidapi.com');
        $this->tertiaryApiKey = config('services.rapidapi_spotify.tertiary_key', $this->apiKey);
    }

    public static function enabled(): bool
    {
        return !empty(config('services.rapidapi_spotify.key')) ||
               !empty(config('services.rapidapi_spotify.primary_key')) ||
               !empty(config('services.rapidapi.key'));
    }

    /**
     * Circuit Breaker: Check if provider should be skipped
     * Returns true if provider has failed too many times recently
     */
    private function shouldSkipProvider(string $provider): bool
    {
        $cacheKey = "rapidapi_circuit_breaker_{$provider}";
        $circuitState = \Cache::get($cacheKey);

        if ($circuitState && $circuitState['status'] === 'open') {
            // Circuit is open (provider disabled), check if recovery time has passed
            if (now()->timestamp >= $circuitState['retry_after']) {
                // 1 hour passed, allow user to try again (but keep circuit open)
                \Log::info("🔌 [CIRCUIT BREAKER] {$provider} available for user retry", [
                    'disabled_at' => $circuitState['disabled_at'],
                    'retry_after' => date('Y-m-d H:i:s', $circuitState['retry_after'])
                ]);
                return false; // Allow this attempt
            }

            \Log::warning("🔌 [CIRCUIT BREAKER] Skipping {$provider} - circuit open", [
                'retry_after' => date('Y-m-d H:i:s', $circuitState['retry_after'])
            ]);
            return true; // Skip this provider
        }

        return false; // Circuit closed, provider is healthy
    }

    /**
     * Circuit Breaker: Record failure
     */
    private function recordFailure(string $provider): void
    {
        $cacheKey = "rapidapi_circuit_breaker_{$provider}";
        $failureKey = "rapidapi_failures_{$provider}";

        // Get current failure count in last 5 minutes
        $failures = \Cache::get($failureKey, []);
        $failures[] = now()->timestamp;

        // Keep only failures from last 5 minutes
        $fiveMinutesAgo = now()->subMinutes(5)->timestamp;
        $failures = array_filter($failures, fn($time) => $time >= $fiveMinutesAgo);

        \Cache::put($failureKey, $failures, now()->addMinutes(5));

        // If 3+ failures in last 5 minutes, open circuit (disable for 1 hour)
        if (count($failures) >= 3) {
            $retryAfter = now()->addHour()->timestamp;
            \Cache::put($cacheKey, [
                'status' => 'open',
                'disabled_at' => now()->toISOString(),
                'retry_after' => $retryAfter,
                'failure_count' => count($failures)
            ], now()->addHours(2)); // Keep state for 2 hours

            \Log::error("🔌 [CIRCUIT BREAKER] Opening circuit for {$provider}", [
                'failure_count' => count($failures),
                'disabled_until' => date('Y-m-d H:i:s', $retryAfter)
            ]);
        }
    }

    /**
     * Circuit Breaker: Record success (close circuit)
     */
    private function recordSuccess(string $provider): void
    {
        $cacheKey = "rapidapi_circuit_breaker_{$provider}";
        $failureKey = "rapidapi_failures_{$provider}";

        // Clear failures and close circuit
        \Cache::forget($failureKey);
        \Cache::forget($cacheKey);

        \Log::info("🔌 [CIRCUIT BREAKER] Closing circuit for {$provider} - provider healthy");
    }

    /**
     * Normalize provider name for cache keys (consistent lower-case)
     */
    private function normalizeProviderKey(string $provider, string $host): string
    {
        return strtolower($provider ?: $host);
    }

    /**
     * Build cache key for per-provider/per-second rate limiting
     */
    private function getRateLimitKey(string $providerKey): string
    {
        $window = now()->format('YmdHis');
        return "rapidapi_rps_{$providerKey}_{$window}";
    }

    /**
     * Attempt to reserve a rate limit slot for the provider.
     * Returns true if provider can be used in this second.
     */
    private function tryReserveRateLimit(string $providerKey): bool
    {
        $cacheKey = $this->getRateLimitKey($providerKey);
        $expiresAt = now()->addSeconds($this->rateLimitWindowSeconds + 1);

        // Initialize counter for the window if missing
        \Cache::add($cacheKey, 0, $expiresAt);

        // Short-circuit if already at or above the limit
        $current = (int) \Cache::get($cacheKey, 0);
        if ($current >= $this->providerRateLimitPerSecond) {
            return false;
        }

        // Reserve a slot (may still race but keeps us under the soft cap)
        $newCount = \Cache::increment($cacheKey);
        return $newCount !== false && $newCount <= $this->providerRateLimitPerSecond;
    }

    /**
     * Optional tiny wait to roll into the next second to avoid a hard "retry" response.
     */
    private function waitForNextWindowAndReserve(string $providerKey): bool
    {
        $microsToNextSecond = (int) ((ceil(microtime(true)) - microtime(true)) * 1_000_000);

        if ($microsToNextSecond > 0 && $microsToNextSecond <= $this->rateLimitGraceMs * 1000) {
            usleep($microsToNextSecond);
            return $this->tryReserveRateLimit($providerKey);
        }

        return false;
    }

    /**
     * Search for tracks with primary/backup fallback
     *
     * @param string $query Search query (artist + track name)
     * @param int $limit Number of results (default: 10)
     * @param string $type Type of search (default: 'tracks')
     * @param int $offset Offset for pagination (default: 0)
     * @return array
     */
    public function searchTracks(string $query, int $limit = 20, string $type = 'tracks', int $offset = 0): array
    {
        try {
            \Log::info('🔍 [RAPIDAPI SPOTIFY] Starting track search with 3-tier backup', [
                'query' => $query,
                'type' => $type,
                'limit' => $limit,
                'offset' => $offset,
                'timestamp' => now()->toISOString()
            ]);

            // Build request parameters
            $params = ['q' => $query, 'type' => $type, 'limit' => $limit];
            if ($offset > 0) {
                $params['offset'] = $offset;
            }

            // Try primary API first (spotify81)
            $result = $this->makeRequest(
                '/search',
                $params,
                $this->primaryHost,
                $this->apiKey,
                'Spotify81'
            );

            if ($result['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Primary search successful', [
                    'provider' => 'Spotify81',
                    'query' => $query,
                    'offset' => $offset
                ]);
                return $result;
            }

            // If primary failed, try backup (spotify-web2)
            \Log::warning('🔍 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'primary_error' => $result['error'] ?? 'Unknown error',
                'backup_provider' => 'SpotifyWeb2'
            ]);

            $backupResult = $this->makeRequest(
                '/search',
                $params,
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backupResult['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Backup search successful', [
                    'provider' => 'SpotifyWeb2',
                    'query' => $query,
                    'offset' => $offset
                ]);
                return $backupResult;
            }

            // If backup failed, try tertiary (spotify23)
            \Log::warning('🔍 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                'backup_error' => $backupResult['error'] ?? 'Unknown error',
                'tertiary_provider' => 'Spotify23'
            ]);

            $tertiaryResult = $this->makeRequest(
                '/search',
                $params,
                $this->tertiaryHost,
                $this->tertiaryApiKey,
                'Spotify23'
            );

            if ($tertiaryResult['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Tertiary search successful', [
                    'provider' => 'Spotify23',
                    'query' => $query,
                    'offset' => $offset
                ]);
                return $tertiaryResult;
            }

            // All APIs failed
            \Log::error('🔍 [RAPIDAPI SPOTIFY] All search APIs failed', [
                'query' => $query,
                'offset' => $offset,
                'primary_error' => $result['error'] ?? 'Unknown',
                'backup_error' => $backupResult['error'] ?? 'Unknown',
                'tertiary_error' => $tertiaryResult['error'] ?? 'Unknown'
            ]);

            return ['success' => false, 'error' => 'All search APIs failed'];

        } catch (\Exception $e) {
            \Log::error('🔍 [RAPIDAPI SPOTIFY] Search failed with exception', [
                'query' => $query,
                'offset' => $offset,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert track ID to playlist URI using seed_to_playlist endpoint
     * Uses 3-tier backup system for high availability
     *
     * @param string $trackId Spotify track ID
     * @return array
     */
    public function seedToPlaylist(string $trackId): array
    {
        try {
            \Log::info('🎧 [RAPIDAPI SPOTIFY] Starting seed-to-playlist with 3-tier backup', [
                'track_id' => $trackId,
                'uri' => "spotify:track:$trackId",
                'timestamp' => now()->toISOString()
            ]);

            // Try primary API first (spotify81)
            $result = $this->makeRequest(
                '/seed_to_playlist',
                ['uri' => "spotify:track:$trackId"],
                $this->primaryHost,
                $this->apiKey,
                'Spotify81'
            );

            if ($result['success']) {
                \Log::info('🎧 [RAPIDAPI SPOTIFY] Primary seed-to-playlist successful', [
                    'provider' => 'Spotify81',
                    'track_id' => $trackId
                ]);
                return $result;
            }

            // If primary failed, try backup (spotify-web2)
            \Log::warning('🎧 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'primary_error' => $result['error'] ?? 'Unknown error',
                'backup_provider' => 'SpotifyWeb2'
            ]);

            $backupResult = $this->makeRequest(
                '/seed_to_playlist',
                ['uri' => "spotify:track:$trackId"],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backupResult['success']) {
                \Log::info('🎧 [RAPIDAPI SPOTIFY] Backup seed-to-playlist successful', [
                    'provider' => 'SpotifyWeb2',
                    'track_id' => $trackId
                ]);
                return $backupResult;
            }

            // If backup failed, try tertiary (spotify23)
            \Log::warning('🎧 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                'backup_error' => $backupResult['error'] ?? 'Unknown error',
                'tertiary_provider' => 'Spotify23'
            ]);

            $tertiaryResult = $this->makeRequest(
                '/seed_to_playlist',
                ['uri' => "spotify:track:$trackId"],
                $this->tertiaryHost,
                $this->tertiaryApiKey,
                'Spotify23'
            );

            if ($tertiaryResult['success']) {
                \Log::info('🎧 [RAPIDAPI SPOTIFY] Tertiary seed-to-playlist successful', [
                    'provider' => 'Spotify23',
                    'track_id' => $trackId
                ]);
                return $tertiaryResult;
            }

            // All APIs failed
            \Log::error('🎧 [RAPIDAPI SPOTIFY] All seed-to-playlist APIs failed', [
                'track_id' => $trackId,
                'primary_error' => $result['error'] ?? 'Unknown',
                'backup_error' => $backupResult['error'] ?? 'Unknown',
                'tertiary_error' => $tertiaryResult['error'] ?? 'Unknown'
            ]);

            return ['success' => false, 'error' => 'All seed-to-playlist APIs failed'];

        } catch (\Exception $e) {
            \Log::error('🎧 [RAPIDAPI SPOTIFY] Seed-to-playlist failed with exception', [
                'track_id' => $trackId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch playlist tracks by playlist ID with 3-tier backup
     *
     * @param string $playlistId Spotify playlist ID
     * @param int $limit Max number of items to request (default 100)
     * @param int $offset Offset for pagination
     * @return array { success: bool, data?: array, error?: string }
     */
    public function getPlaylistTracks(string $playlistId, int $limit = 100, int $offset = 0): array
    {
        try {
            \Log::info('🎧 [RAPIDAPI SPOTIFY] Fetching playlist tracks with 3-tier backup', [
                'playlist_id' => $playlistId,
                'limit' => $limit,
                'offset' => $offset,
                'timestamp' => now()->toISOString()
            ]);

            $params = [
                'id' => $playlistId,
                'offset' => max(0, $offset),
                'limit' => min(max(1, $limit), 100),
            ];

            // Primary
            $primary = $this->makeRequest(
                '/playlist_tracks',
                $params,
                $this->primaryHost,
                $this->apiKey,
                'Spotify81'
            );
            if ($primary['success']) {
                return $primary;
            }

            // Backup
            \Log::warning('🎧 [RAPIDAPI SPOTIFY] Primary playlist_tracks failed, trying backup', [
                'primary_error' => $primary['error'] ?? 'Unknown error'
            ]);
            $backup = $this->makeRequest(
                '/playlist_tracks',
                $params,
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );
            if ($backup['success']) {
                return $backup;
            }

            // Tertiary
            \Log::warning('🎧 [RAPIDAPI SPOTIFY] Backup playlist_tracks failed, trying tertiary', [
                'backup_error' => $backup['error'] ?? 'Unknown error'
            ]);
            $tertiary = $this->makeRequest(
                '/playlist_tracks',
                $params,
                $this->tertiaryHost,
                $this->tertiaryApiKey,
                'Spotify23'
            );
            if ($tertiary['success']) {
                return $tertiary;
            }

            \Log::error('🎧 [RAPIDAPI SPOTIFY] All playlist_tracks providers failed', [
                'primary_error' => $primary['error'] ?? 'Unknown',
                'backup_error' => $backup['error'] ?? 'Unknown',
                'tertiary_error' => $tertiary['error'] ?? 'Unknown'
            ]);
            return ['success' => false, 'error' => 'All playlist_tracks providers failed'];
        } catch (\Exception $e) {
            \Log::error('🎧 [RAPIDAPI SPOTIFY] playlist_tracks failed with exception', [
                'playlist_id' => $playlistId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get track details by ID
     *
     * @param string $trackId Spotify track ID
     * @return array
     */
    public function getTrackById(string $trackId): array
    {
        try {
            // Try primary API
            $result = $this->makeRequest(
                '/tracks',
                ['ids' => $trackId],
                $this->primaryHost,
                $this->apiKey,
                'spotify81'
            );

            if ($result['success']) {
                // Extract first track from response
                $tracks = $result['data']['tracks'] ?? [];
                if (!empty($tracks)) {
                    return ['success' => true, 'data' => $tracks[0]];
                }
            }

            // Fallback to backup
            Log::warning('Primary track fetch failed, trying backup');

            $result = $this->makeRequest(
                '/tracks',
                ['ids' => $trackId],
                $this->backupHost,
                $this->backupApiKey,
                'spotify-web2'
            );

            if ($result['success']) {
                $tracks = $result['data']['tracks'] ?? [];
                if (!empty($tracks)) {
                    return ['success' => true, 'data' => $tracks[0]];
                }
            }

            return ['success' => false, 'error' => 'Track not found'];

        } catch (\Exception $e) {
            Log::error('Failed to get track by ID', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get artist details by ID (for followers count)
     *
     * @param string $artistId Spotify artist ID
     * @return array
     */
    public function getArtistById(string $artistId): array
    {
        try {
            // Try primary API
            $result = $this->makeRequest(
                '/artists',
                ['ids' => $artistId],
                $this->primaryHost,
                $this->apiKey,
                'spotify81'
            );

            if ($result['success']) {
                $artists = $result['data']['artists'] ?? [];
                if (!empty($artists)) {
                    return ['success' => true, 'data' => $artists[0]];
                }
            }

            // Fallback to backup
            Log::warning('Primary artist fetch failed, trying backup');

            $result = $this->makeRequest(
                '/artists',
                ['ids' => $artistId],
                $this->backupHost,
                $this->backupApiKey,
                'spotify-web2'
            );

            if ($result['success']) {
                $artists = $result['data']['artists'] ?? [];
                if (!empty($artists)) {
                    return ['success' => true, 'data' => $artists[0]];
                }
            }

            return ['success' => false, 'error' => 'Artist not found'];

        } catch (\Exception $e) {
            Log::error('Failed to get artist by ID', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get album details by ID (for release date)
     *
     * @param string $albumId Spotify album ID
     * @return array
     */
    public function getAlbumById(string $albumId): array
    {
        try {
            // Try primary API
            $result = $this->makeRequest(
                '/albums',
                ['ids' => $albumId],
                $this->primaryHost,
                $this->apiKey,
                'spotify81'
            );

            if ($result['success']) {
                $albums = $result['data']['albums'] ?? [];
                if (!empty($albums)) {
                    return ['success' => true, 'data' => $albums[0]];
                }
            }

            // Fallback to backup
            Log::warning('Primary album fetch failed, trying backup');

            $result = $this->makeRequest(
                '/albums',
                ['ids' => $albumId],
                $this->backupHost,
                $this->backupApiKey,
                'spotify-web2'
            );

            if ($result['success']) {
                $albums = $result['data']['albums'] ?? [];
                if (!empty($albums)) {
                    return ['success' => true, 'data' => $albums[0]];
                }
            }

            return ['success' => false, 'error' => 'Album not found'];

        } catch (\Exception $e) {
            Log::error('Failed to get album by ID', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Make request to RapidAPI with rate limiting
     *
     * @param string $endpoint API endpoint path
     * @param array $params Query parameters
     * @param string $host RapidAPI host
     * @param string $apiKey RapidAPI key
     * @param string $provider Provider name for logging
     * @return array
     */
    private function makeRequest(
        string $endpoint,
        array $params,
        string $host,
        string $apiKey,
        string $provider
    ): array {
        $providerKey = $this->normalizeProviderKey($provider, $host);

        // Circuit Breaker: Check if this provider should be skipped
        if ($this->shouldSkipProvider($providerKey)) {
            return [
                'success' => false,
                'error' => 'Provider temporarily disabled (circuit breaker)',
                'circuit_open' => true
            ];
        }

        // Rate limiting: cap each provider at 5 req/sec
        $hasSlot = $this->tryReserveRateLimit($providerKey);
        if (!$hasSlot) {
            // Try to slip into the next second briefly; otherwise bail fast
            if (!$this->waitForNextWindowAndReserve($providerKey)) {
                Log::warning("Rate limit reached locally for {$provider}", [
                    'provider_key' => $providerKey,
                    'endpoint' => $endpoint
                ]);

                return [
                    'success' => false,
                    'error' => 'Rate limit reached for provider - please retry shortly',
                    'rate_limit_reached' => true
                ];
            }
        }

        // Remove leading slash if present to avoid double slashes
        $endpoint = ltrim($endpoint, '/');

        // Build query string manually for endpoints that need it in the URL path
        $queryString = '';
        if (!empty($params)) {
            $parts = [];
            foreach ($params as $key => $value) {
                $parts[] = $key . '=' . $value;  // No urlencode() - keep commas as-is
            }
            $queryString = '?' . implode('&', $parts);
        }
        $url = "https://{$host}/{$endpoint}{$queryString}";

        $headers = [
            'X-RapidAPI-Key' => $apiKey,
            'X-RapidAPI-Host' => $host,
        ];

        try {
            Log::info("🔥 RAPIDAPI_REQUEST_{$provider}", [
                'endpoint' => $endpoint,
                'params' => $params,
                'full_url' => $url,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(10) // Reduced from 30s to 10s for faster failover
                ->get($url);

            // Rate limiting delay
            usleep($this->rateLimitDelay * 1000000);

            Log::info("🔥 RAPIDAPI_RESPONSE_{$provider}", [
                'status' => $response->status(),
                'success' => $response->successful(),
                'timestamp' => now()->toISOString()
            ]);

            if ($response->successful()) {
                // Circuit Breaker: Record success (close circuit if it was open)
                $this->recordSuccess($providerKey);
                return ['success' => true, 'data' => $response->json()];
            }

            // Check for rate limit (429)
            if ($response->status() === 429) {
                Log::warning("Rate limit exceeded on {$provider}", [
                    'endpoint' => $endpoint
                ]);
                // Do NOT trigger circuit breaker on expected 429 throttle
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'status' => 429,
                    'rate_limit_exceeded' => true
                ];
            }

            // Other errors (4xx, 5xx)
            // Circuit Breaker: Record failure
            $this->recordFailure($providerKey);
            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("RapidAPI request exception ({$provider})", [
                'error' => $e->getMessage()
            ]);
            // Circuit Breaker: Record failure (timeout, connection error, etc.)
            $this->recordFailure($providerKey);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Clean track name for better matching
     * Removes version info, featuring artists, etc.
     */
    public function cleanTrackName(string $name): string
    {
        // Check if this is a specific remix we want to keep
        $hasSpecificRemix = preg_match('/\([^)]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^)]*\)/i', $name) ||
                           preg_match('/\[[^\]]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^\]]*\]/i', $name);

        if ($hasSpecificRemix) {
            // Keep specific remixes, only clean spacing
            return preg_replace('/\s+/', ' ', trim($name));
        }

        // Remove generic version content
        $cleaned = preg_replace('/\s*\([^)]*(?:original|version|radio|extended|edit)(?!\s+remix)[^)]*\)/i', '', $name);
        $cleaned = preg_replace('/\s*\[[^\]]*(?:original|version|radio|extended|edit)(?!\s+remix)[^\]]*\]/i', '', $cleaned);

        // Clean up spacing
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = preg_replace('/\s*-\s*$/', '', $cleaned);
        $cleaned = preg_replace('/^\s*-\s*/', '', $cleaned);

        return trim($cleaned);
    }

    /**
     * Match exact artist and title from search results
     *
     * Rules:
     * - Title: Must be exact match (only letters/numbers)
     * - Artist: Must match or start with expected artist + featuring indicators
     *   ✅ "Eminem" matches "Eminem"
     *   ✅ "Eminem feat. Dido" matches "Eminem"
     *   ❌ "Eminem XYZ" does NOT match "Eminem"
     *
     * @param array $searchResults Results from searchTracks()
     * @param string $artistName Expected artist name
     * @param string $trackTitle Expected track title
     * @return string|null Spotify track ID if found
     */
    public function findExactMatch(array $searchResults, string $artistName, string $trackTitle): ?string
    {
        // Handle different provider response structures
        // Official Spotify structure: data.tracks.items[]
        // RapidAPI structure: data.tracks[]
        if (!isset($searchResults['data']['tracks']) || !is_array($searchResults['data']['tracks'])) {
            Log::warning("🔥 RapidAPI: No tracks in search results", [
                'expected_path' => 'data.tracks',
                'available_keys' => array_keys($searchResults['data'] ?? $searchResults)
            ]);
            return null;
        }

        // Detect structure and extract tracks array
        $isOfficialStructure = isset($searchResults['data']['tracks']['items']);
        $tracks = $isOfficialStructure
            ? ($searchResults['data']['tracks']['items'] ?? [])
            : ($searchResults['data']['tracks'] ?? []);

        $cleanedExpectedArtist = $this->normalizeString($artistName);
        $cleanedExpectedTitle = $this->normalizeString($trackTitle);

        Log::info("🔥 RapidAPI: Looking for exact match", [
            'expected_artist' => $artistName,
            'expected_title' => $trackTitle,
            'normalized_artist' => $cleanedExpectedArtist,
            'normalized_title' => $cleanedExpectedTitle,
            'total_results' => count($tracks),
            'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi'
        ]);

        foreach ($tracks as $index => $trackWrapper) {
            // Each track might be wrapped in a 'data' object (RapidAPI structure)
            $track = $trackWrapper['data'] ?? $trackWrapper;

            // Detect structure and extract artist name
            // RapidAPI: artists.items[0].profile.name
            // Official Spotify: artists[0].name
            $isRapidApiStructure = isset($track['artists']['items']);
            $trackArtist = $isRapidApiStructure
                ? ($track['artists']['items'][0]['profile']['name'] ?? '')
                : ($track['artists'][0]['name'] ?? '');
            $trackName = $track['name'] ?? '';
            $trackId = $track['id'] ?? null;

            $cleanedTrackArtist = $this->normalizeString($trackArtist);
            $cleanedTrackName = $this->normalizeString($trackName);

            // Title matching logic:
            // 1. EXACT match, OR
            // 2. Spotify title starts with expected title + has featuring info
            //    Example: Expected "I Know What You Want" matches "I Know What You Want (feat. Mariah Carey)"
            $titleMatch = false;

            if ($cleanedTrackName === $cleanedExpectedTitle) {
                // Exact match
                $titleMatch = true;
            } elseif (str_starts_with($cleanedTrackName, $cleanedExpectedTitle)) {
                // Check if remainder is featuring info (starts with feat, ft, with, etc.)
                $remainder = trim(substr($cleanedTrackName, strlen($cleanedExpectedTitle)));

                $featuringIndicators = ['feat', 'ft', 'featuring', 'with', 'x'];

                foreach ($featuringIndicators as $indicator) {
                    if (str_starts_with($remainder, $indicator)) {
                        $titleMatch = true;
                        break;
                    }
                }
            }

            // Artist matching logic:
            // 1. Exact match, OR
            // 2. Starts with expected artist + featuring indicators (feat, ft, featuring, with, and, x)
            $artistMatch = false;

            if ($cleanedTrackArtist === $cleanedExpectedArtist) {
                // Exact match
                $artistMatch = true;
            } elseif (str_starts_with($cleanedTrackArtist, $cleanedExpectedArtist)) {
                // Check if remainder starts with featuring indicators
                $remainder = trim(substr($cleanedTrackArtist, strlen($cleanedExpectedArtist)));

                // Valid featuring indicators
                $featuringIndicators = ['feat', 'ft', 'featuring', 'with', 'and', 'x'];

                foreach ($featuringIndicators as $indicator) {
                    if (str_starts_with($remainder, $indicator)) {
                        $artistMatch = true;
                        break;
                    }
                }
            }

            Log::info("🔥 RapidAPI: Comparing track #{$index}", [
                'track_id' => $trackId,
                'track_artist' => $trackArtist,
                'track_name' => $trackName,
                'normalized_artist' => $cleanedTrackArtist,
                'normalized_name' => $cleanedTrackName,
                'artist_match' => $artistMatch,
                'title_match' => $titleMatch
            ]);

            // Match if both artist and title match
            if ($artistMatch && $titleMatch) {
                Log::info("🔥 RapidAPI: Found exact match!", ['track_id' => $trackId]);
                return $trackId;
            }
        }

        Log::warning("🔥 RapidAPI: No exact match found among results");
        return null;
    }

    /**
     * Search for artists using RapidAPI Spotify
     * GET /search?q={query}&type=artists&limit={limit}
     */
    public function searchArtists(string $query, int $limit = 20): array
    {
        try {
            \Log::info('🔍 [RAPIDAPI SPOTIFY] Starting artist search with 3-tier backup', [
                'query' => $query,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            $params = [
                'q' => $query,
                'type' => 'artists',
                'limit' => min($limit, 50) // API limit
            ];

            $providers = [
                ['host' => $this->primaryHost, 'key' => $this->apiKey, 'name' => 'Spotify81'],
                ['host' => $this->backupHost, 'key' => $this->backupApiKey, 'name' => 'SpotifyWeb2'],
                ['host' => $this->tertiaryHost, 'key' => $this->tertiaryApiKey, 'name' => 'Spotify23'],
            ];

            foreach ($providers as $provider) {
                $result = $this->makeRequest(
                    '/search',
                    $params,
                    $provider['host'],
                    $provider['key'],
                    $provider['name']
                );

                if ($result['success']) {
                    // Detect structure and extract artists array
                    $isOfficialStructure = isset($result['data']['artists']['items']);
                    $artistsData = $isOfficialStructure
                        ? ($result['data']['artists']['items'] ?? [])
                        : ($result['data']['artists'] ?? []);

                    if (!empty($artistsData)) {
                        $artists = $this->formatArtistsArray($artistsData);
                        \Log::info('🔍 [RAPIDAPI SPOTIFY] Artist search successful', [
                            'provider' => $provider['name'],
                            'query' => $query,
                            'found_count' => count($artists),
                            'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi',
                            'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $artists), 0, 3)
                        ]);
                        return $artists;
                    }
                } else {
                    \Log::warning('🔍 [RAPIDAPI SPOTIFY] Artist search provider failed', [
                        'provider' => $provider['name'],
                        'query' => $query,
                        'error' => $result['error'] ?? 'Unknown error',
                        'status' => $result['status'] ?? null,
                        'rate_limit_reached' => $result['rate_limit_reached'] ?? false,
                        'rate_limit_exceeded' => $result['rate_limit_exceeded'] ?? false
                    ]);
                }
            }

            \Log::error('🔍 [RAPIDAPI SPOTIFY] All artist search providers failed', [
                'query' => $query
            ]);
            return [];
        } catch (\Exception $e) {
            \Log::error('🔍 [RAPIDAPI SPOTIFY] Artist search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Search for albums using RapidAPI Spotify with 3-tier backup
     */
    public function searchAlbums(string $query, int $limit = 20): array
    {
        try {
            \Log::info('🔍 [RAPIDAPI SPOTIFY] Starting album search with 3-tier backup', [
                'query' => $query,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            $params = [
                'q' => $query,
                'type' => 'albums',
                'limit' => min($limit, 50)
            ];

            $providers = [
                ['host' => $this->primaryHost, 'key' => $this->apiKey, 'name' => 'Spotify81'],
                ['host' => $this->backupHost, 'key' => $this->backupApiKey, 'name' => 'SpotifyWeb2'],
                ['host' => $this->tertiaryHost, 'key' => $this->tertiaryApiKey, 'name' => 'Spotify23'],
            ];

            foreach ($providers as $provider) {
                $result = $this->makeRequest(
                    '/search',
                    $params,
                    $provider['host'],
                    $provider['key'],
                    $provider['name']
                );

                if ($result['success']) {
                    $data = $result['data'] ?? [];

                    // Detect structure and extract albums array
                    // Official Spotify: data.albums.items[]
                    // RapidAPI: data.albums[] or albums.items[]
                    $albumsData = null;
                    $structure = 'unknown';

                    if (isset($data['albums']['items'])) {
                        $albumsData = $data['albums']['items'];
                        $structure = 'official_spotify';
                    } elseif (isset($data['albums']) && is_array($data['albums'])) {
                        $albumsData = $data['albums'];
                        $structure = 'rapidapi_albums_array';
                    } elseif (isset($data['data']['albums']['items'])) {
                        $albumsData = $data['data']['albums']['items'];
                        $structure = 'double_wrapped';
                    }

                    if (!empty($albumsData)) {
                        $formattedAlbums = $this->formatAlbumsArray($albumsData);
                        \Log::info('🔍 [RAPIDAPI SPOTIFY] Album search successful', [
                            'provider' => $provider['name'],
                            'query' => $query,
                            'found_count' => count($formattedAlbums),
                            'structure' => $structure
                        ]);

                        return [
                            'success' => true,
                            'albums' => [
                                'items' => $formattedAlbums,
                                'total' => count($formattedAlbums)
                            ]
                        ];
                    }
                } else {
                    \Log::warning('🔍 [RAPIDAPI SPOTIFY] Album search provider failed', [
                        'provider' => $provider['name'],
                        'query' => $query,
                        'error' => $result['error'] ?? 'Unknown error',
                        'status' => $result['status'] ?? null,
                        'rate_limit_reached' => $result['rate_limit_reached'] ?? false,
                        'rate_limit_exceeded' => $result['rate_limit_exceeded'] ?? false
                    ]);
                }
            }

            \Log::error('🔍 [RAPIDAPI SPOTIFY] All album search providers failed', [
                'query' => $query
            ]);
            return ['success' => false, 'albums' => ['items' => []]];
        } catch (\Exception $e) {
            \Log::error('🔍 [RAPIDAPI SPOTIFY] Album search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'albums' => ['items' => []]];
        }
    }

    /**
     * Get similar artists recommendations using RapidAPI Spotify with 3-tier backup
     * Uses artist_related endpoint
     */
    public function getSimilarArtists(string $artistId, int $limit = 20): array
    {
        try {
            \Log::info('🎵 [RAPIDAPI SPOTIFY] Getting similar artists with 3-tier backup', [
                'artist_id' => $artistId,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            // Try primary API first (spotify81)
            $result = $this->makeRequest(
                '/artist_related',
                ['id' => $artistId],
                $this->primaryHost,
                $this->apiKey,
                'Spotify81'
            );

            if ($result['success']) {
                // Detect structure and extract artists array
                // RapidAPI Spotify81 structure: data.artist.relatedContent.relatedArtists.items
                $artistsData = null;
                $structure = 'unknown';
                
                // Debug: Log the top-level keys to understand the structure
                if (isset($result['data'])) {
                    \Log::debug('🎵 [RAPIDAPI SPOTIFY] Spotify81 response structure check', [
                        'top_level_keys' => array_keys($result['data']),
                        'has_artist' => isset($result['data']['artist']),
                        'has_artists' => isset($result['data']['artists']),
                    ]);
                    
                    // Check for RapidAPI Spotify81 nested structure (data.artist.relatedContent.relatedArtists.items)
                    if (isset($result['data']['artist']['relatedContent']['relatedArtists']['items'])) {
                        $artistsData = $result['data']['artist']['relatedContent']['relatedArtists']['items'];
                        $structure = 'rapidapi_spotify81';
                        \Log::debug('🎵 [RAPIDAPI SPOTIFY] Found rapidapi_spotify81 structure', [
                            'items_count' => is_array($artistsData) ? count($artistsData) : 0
                        ]);
                    } 
                    // Check if response is already the nested structure (double data wrapping)
                    elseif (isset($result['data']['data']['artist']['relatedContent']['relatedArtists']['items'])) {
                        $artistsData = $result['data']['data']['artist']['relatedContent']['relatedArtists']['items'];
                        $structure = 'rapidapi_spotify81_double_wrapped';
                        $itemsCount = is_array($artistsData) ? count($artistsData) : 0;
                        \Log::debug('🎵 [RAPIDAPI SPOTIFY] Found rapidapi_spotify81 structure (double wrapped)', [
                            'items_count' => $itemsCount
                        ]);
                        
                        // If items array is empty, log the full structure to debug
                        if ($itemsCount === 0) {
                            $relatedArtists = $result['data']['data']['artist']['relatedContent']['relatedArtists'] ?? null;
                            $totalCount = $relatedArtists['totalCount'] ?? null;
                            
                            \Log::warning('🎵 [RAPIDAPI SPOTIFY] Empty items array detected - logging full structure', [
                                'full_path_exists' => isset($result['data']['data']['artist']['relatedContent']['relatedArtists']),
                                'totalCount' => $totalCount,
                                'totalCount_type' => gettype($totalCount),
                                'items_is_array' => is_array($artistsData),
                                'items_count' => $itemsCount,
                                'relatedArtists_keys' => isset($result['data']['data']['artist']['relatedContent']['relatedArtists']) 
                                    ? array_keys($result['data']['data']['artist']['relatedContent']['relatedArtists']) 
                                    : [],
                                'relatedContent_keys' => isset($result['data']['data']['artist']['relatedContent']) 
                                    ? array_keys($result['data']['data']['artist']['relatedContent']) 
                                    : [],
                                'artist_keys' => isset($result['data']['data']['artist']) 
                                    ? array_keys($result['data']['data']['artist']) 
                                    : [],
                                'data_data_keys' => isset($result['data']['data']) 
                                    ? array_keys($result['data']['data']) 
                                    : [],
                            ]);
                            
                            // If totalCount > 0 but items is empty, there might be a pagination or structure issue
                            if ($totalCount !== null && $totalCount > 0 && $itemsCount === 0) {
                                \Log::error('🎵 [RAPIDAPI SPOTIFY] Mismatch: totalCount indicates artists exist but items array is empty', [
                                    'totalCount' => $totalCount,
                                    'items_count' => $itemsCount,
                                    'artist_id' => $artistId,
                                ]);
                            }
                        }
                    }
                    // Official Spotify structure
                    elseif (isset($result['data']['artists']['items'])) {
                        $artistsData = $result['data']['artists']['items'];
                        $structure = 'official_spotify';
                        \Log::debug('🎵 [RAPIDAPI SPOTIFY] Found official_spotify structure', [
                            'items_count' => is_array($artistsData) ? count($artistsData) : 0
                        ]);
                    } 
                    // Direct array structure
                    elseif (isset($result['data']['artists']) && is_array($result['data']['artists'])) {
                        $artistsData = $result['data']['artists'];
                        $structure = 'direct_array';
                        \Log::debug('🎵 [RAPIDAPI SPOTIFY] Found direct_array structure', [
                            'items_count' => is_array($artistsData) ? count($artistsData) : 0
                        ]);
                    } else {
                        // Check for alternative structures - maybe data is at root level
                        if (isset($result['data']) && is_array($result['data'])) {
                            // Try to find any array that might contain artists
                            $possiblePaths = [
                                'data' => $result['data'],
                                'data.data' => $result['data']['data'] ?? null,
                                'data.artist' => $result['data']['artist'] ?? null,
                                'data.artists' => $result['data']['artists'] ?? null,
                            ];
                            
                            foreach ($possiblePaths as $path => $value) {
                                if (is_array($value)) {
                                    // Check if this array contains items that look like artists
                                    if (isset($value['items']) && is_array($value['items'])) {
                                        \Log::info('🎵 [RAPIDAPI SPOTIFY] Found alternative structure at path', [
                                            'path' => $path,
                                            'items_count' => count($value['items'])
                                        ]);
                                        $artistsData = $value['items'];
                                        $structure = 'alternative_' . str_replace('.', '_', $path);
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Log what we actually have for debugging (limit size to avoid huge logs)
                        $dataSummary = $this->summarizeArrayStructure($result['data'] ?? [], 3);
                        \Log::warning('🎵 [RAPIDAPI SPOTIFY] Spotify81 structure not recognized', [
                            'data_keys' => array_keys($result['data'] ?? []),
                            'has_artist_key' => isset($result['data']['artist']),
                            'artist_keys' => isset($result['data']['artist']) ? array_keys($result['data']['artist']) : [],
                            'has_data_data' => isset($result['data']['data']),
                            'data_data_keys' => isset($result['data']['data']) ? array_keys($result['data']['data']) : [],
                            'structure_summary' => $dataSummary,
                        ]);
                    }
                }

                if (!empty($artistsData) && is_array($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Primary similar artists successful', [
                        'provider' => 'Spotify81',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $structure
                    ]);
                    return $artists;
                } else {
                    // Enhanced logging when no artists found
                    $logData = [
                        'artistsData_empty' => empty($artistsData),
                        'artistsData_type' => gettype($artistsData),
                        'artistsData_count' => is_array($artistsData) ? count($artistsData) : 'not_array',
                        'structure_detected' => $structure,
                    ];
                    
                    // If we found a structure but it's empty, log the actual response path
                    if ($structure !== 'unknown' && empty($artistsData)) {
                        if ($structure === 'rapidapi_spotify81_double_wrapped') {
                            $logData['response_path'] = 'data.data.artist.relatedContent.relatedArtists.items';
                            $logData['relatedArtists_exists'] = isset($result['data']['data']['artist']['relatedContent']['relatedArtists']);
                            if (isset($result['data']['data']['artist']['relatedContent']['relatedArtists'])) {
                                $logData['relatedArtists_keys'] = array_keys($result['data']['data']['artist']['relatedContent']['relatedArtists']);
                            }
                        }
                    }
                    
                    \Log::warning('🎵 [RAPIDAPI SPOTIFY] Spotify81 returned success but no artists found', $logData);
                }
            }

            // If primary failed, try backup (spotify-web2)
            \Log::warning('🎵 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'primary_error' => $result['error'] ?? 'Unknown error',
                'backup_provider' => 'SpotifyWeb2'
            ]);

            $backupResult = $this->makeRequest(
                '/artist_related',
                ['id' => $artistId],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backupResult['success']) {
                // Detect structure and extract artists array
                $artistsData = null;
                $structure = 'unknown';
                
                if (isset($backupResult['data']['artist']['relatedContent']['relatedArtists']['items'])) {
                    // RapidAPI Spotify81 structure
                    $artistsData = $backupResult['data']['artist']['relatedContent']['relatedArtists']['items'];
                    $structure = 'rapidapi_spotify81';
                } elseif (isset($backupResult['data']['artists']['items'])) {
                    // Official Spotify structure
                    $artistsData = $backupResult['data']['artists']['items'];
                    $structure = 'official_spotify';
                } elseif (isset($backupResult['data']['artists']) && is_array($backupResult['data']['artists'])) {
                    // Direct array structure
                    $artistsData = $backupResult['data']['artists'];
                    $structure = 'direct_array';
                }

                if (!empty($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Backup similar artists successful', [
                        'provider' => 'SpotifyWeb2',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $structure
                    ]);
                    return $artists;
                }
            }

            // If backup failed, try tertiary (spotify23)
            \Log::warning('🎵 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                'backup_error' => $backupResult['error'] ?? 'Unknown error',
                'tertiary_provider' => 'Spotify23'
            ]);

            $tertiaryResult = $this->makeRequest(
                '/artist_related',
                ['id' => $artistId],
                $this->tertiaryHost,
                $this->tertiaryApiKey,
                'Spotify23'
            );

            if ($tertiaryResult['success']) {
                // Detect structure and extract artists array
                $artistsData = null;
                $structure = 'unknown';
                
                if (isset($tertiaryResult['data']['artist']['relatedContent']['relatedArtists']['items'])) {
                    // RapidAPI Spotify81 structure
                    $artistsData = $tertiaryResult['data']['artist']['relatedContent']['relatedArtists']['items'];
                    $structure = 'rapidapi_spotify81';
                } elseif (isset($tertiaryResult['data']['artists']['items'])) {
                    // Official Spotify structure
                    $artistsData = $tertiaryResult['data']['artists']['items'];
                    $structure = 'official_spotify';
                } elseif (isset($tertiaryResult['data']['artists']) && is_array($tertiaryResult['data']['artists'])) {
                    // Direct array structure
                    $artistsData = $tertiaryResult['data']['artists'];
                    $structure = 'direct_array';
                }

                if (!empty($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Tertiary similar artists successful', [
                        'provider' => 'Spotify23',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $structure
                    ]);
                    return $artists;
                }
            }

            // All APIs failed
            \Log::error('🎵 [RAPIDAPI SPOTIFY] All similar artists APIs failed', [
                'artist_id' => $artistId,
                'primary_error' => $result['error'] ?? 'Unknown',
                'backup_error' => $backupResult['error'] ?? 'Unknown',
                'tertiary_error' => $tertiaryResult['error'] ?? 'Unknown'
            ]);

            return [];
        } catch (\Exception $e) {
            \Log::error('🎵 [RAPIDAPI SPOTIFY] Similar artists failed with exception', [
                'artist_id' => $artistId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get artist followers count using RapidAPI Spotify
     * GET /artists/{artist_id}
     */
    public function getArtistFollowers(string $artistId): array
    {
        try {
            $result = $this->makeRequest(
                "/artists/{$artistId}",
                [],
                'spotify81.p.rapidapi.com',
                config('services.rapidapi.key'),
                'Spotify81'
            );

            if ($result['success'] && isset($result['data'])) {
                return [
                    'id' => $result['data']['id'] ?? null,
                    'name' => $result['data']['name'] ?? null,
                    'followers' => $result['data']['followers']['total'] ?? 0,
                    'popularity' => $result['data']['popularity'] ?? 0,
                    'genres' => $result['data']['genres'] ?? []
                ];
            }

            return [];
        } catch (\Exception $e) {
            \Log::error('RapidAPI Spotify artist followers failed', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get batch artist followers using RapidAPI Spotify
     * Makes individual requests for each artist (API doesn't support batch)
     */
    public function getBatchArtistFollowers(array $artistIds): array
    {
        \Log::info('📊 [RAPIDAPI SPOTIFY] Starting batch artist followers', [
            'artist_count' => count($artistIds),
            'timestamp' => now()->toISOString()
        ]);

        $ids = array_values(array_filter(array_map('trim', $artistIds)));
        if (empty($ids)) {
            return [];
        }

        $allResults = [];

        // Make a single batch request with all artist IDs comma-separated
        $idsString = implode(',', $ids);

        // Primary: spotify81
        $primary = $this->makeRequest(
            "/artists",
            ['ids' => $idsString],
            $this->primaryHost,
            $this->apiKey,
            'Spotify81'
        );

        if ($primary['success'] && isset($primary['data']['artists']) && is_array($primary['data']['artists'])) {
            foreach ($primary['data']['artists'] as $artist) {
                if ($artist !== null && isset($artist['id'])) {
                    $allResults[$artist['id']] = [
                        'id' => $artist['id'],
                        'name' => $artist['name'] ?? null,
                        'followers' => $artist['followers']['total'] ?? 0,
                        'popularity' => $artist['popularity'] ?? 0,
                    ];
                }
            }
        } else {
            // Backup: spotify-web2
            \Log::info('📊 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'timestamp' => now()->toISOString()
            ]);

            $backup = $this->makeRequest(
                "/artists/",
                ['ids' => $idsString],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backup['success'] && isset($backup['data']['artists']) && is_array($backup['data']['artists'])) {
                foreach ($backup['data']['artists'] as $artist) {
                    if ($artist !== null && isset($artist['id'])) {
                        $allResults[$artist['id']] = [
                            'id' => $artist['id'],
                            'name' => $artist['name'] ?? null,
                            'followers' => $artist['followers']['total'] ?? 0,
                            'popularity' => $artist['popularity'] ?? 0,
                        ];
                    }
                }
            } else {
                // Tertiary: spotify23
                \Log::info('📊 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                    'timestamp' => now()->toISOString()
                ]);

                $tertiary = $this->makeRequest(
                    "/artists/",
                    ['ids' => $idsString],
                    $this->tertiaryHost,
                    $this->tertiaryApiKey,
                    'Spotify23'
                );

                if ($tertiary['success'] && isset($tertiary['data']['artists']) && is_array($tertiary['data']['artists'])) {
                    foreach ($tertiary['data']['artists'] as $artist) {
                        if ($artist !== null && isset($artist['id'])) {
                            $allResults[$artist['id']] = [
                                'id' => $artist['id'],
                                'name' => $artist['name'] ?? null,
                                'followers' => $artist['followers']['total'] ?? 0,
                                'popularity' => $artist['popularity'] ?? 0,
                            ];
                        }
                    }
                }
            }
        }

        \Log::info('📊 [RAPIDAPI SPOTIFY] Batch artist followers completed', [
            'requested' => count($ids),
            'returned' => count($allResults)
        ]);

        return $allResults;
    }

    /**
     * Batch get track details (including popularity) using RapidAPI Spotify
     * Similar to getBatchArtistFollowers but for tracks
     *
     * @param array $trackIds Array of Spotify track IDs
     * @return array Map of track_id => ['id', 'name', 'popularity', 'preview_url', ...]
     */
    public function getBatchTracks(array $trackIds): array
    {
        \Log::info('🎵 [RAPIDAPI SPOTIFY] Starting batch track details', [
            'track_count' => count($trackIds),
            'timestamp' => now()->toISOString()
        ]);

        $ids = array_values(array_filter(array_map('trim', $trackIds)));
        if (empty($ids)) {
            return [];
        }

        $allResults = [];

        // Make a single batch request with all track IDs comma-separated
        $idsString = implode(',', $ids);

        // Primary: spotify81
        $primary = $this->makeRequest(
            "/tracks",
            ['ids' => $idsString],
            $this->primaryHost,
            $this->apiKey,
            'Spotify81'
        );

        if ($primary['success'] && isset($primary['data']['tracks']) && is_array($primary['data']['tracks'])) {
            // Log first track structure for debugging
            if (!empty($primary['data']['tracks'][0])) {
                $firstTrack = $primary['data']['tracks'][0];
                \Log::info('🎵 [BATCH TRACKS] First track structure', [
                    'has_popularity' => isset($firstTrack['popularity']),
                    'popularity_value' => $firstTrack['popularity'] ?? 'NOT_SET',
                    'popularity_type' => isset($firstTrack['popularity']) ? gettype($firstTrack['popularity']) : 'NOT_SET',
                    'track_keys' => array_keys($firstTrack),
                    'track_id' => $firstTrack['id'] ?? 'NOT_SET',
                ]);
            }
            
            foreach ($primary['data']['tracks'] as $track) {
                if ($track !== null && isset($track['id'])) {
                    $allResults[$track['id']] = [
                        'id' => $track['id'],
                        'name' => $track['name'] ?? null,
                        'popularity' => $track['popularity'] ?? 0,
                        'preview_url' => $track['preview_url'] ?? null,
                        'external_urls' => $track['external_urls'] ?? [],
                        'external_ids' => $track['external_ids'] ?? [],
                    ];
                }
            }
        } else {
            // Backup: spotify-web2
            \Log::info('🎵 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'timestamp' => now()->toISOString()
            ]);

            $backup = $this->makeRequest(
                "/tracks",
                ['ids' => $idsString],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backup['success'] && isset($backup['data']['tracks']) && is_array($backup['data']['tracks'])) {
                foreach ($backup['data']['tracks'] as $track) {
                    if ($track !== null && isset($track['id'])) {
                        $allResults[$track['id']] = [
                            'id' => $track['id'],
                            'name' => $track['name'] ?? null,
                            'popularity' => $track['popularity'] ?? 0,
                            'preview_url' => $track['preview_url'] ?? null,
                            'external_urls' => $track['external_urls'] ?? [],
                            'external_ids' => $track['external_ids'] ?? [],
                        ];
                    }
                }
            } else {
                // Tertiary: spotify23
                \Log::info('🎵 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                    'timestamp' => now()->toISOString()
                ]);

                $tertiary = $this->makeRequest(
                    "/tracks",
                    ['ids' => $idsString],
                    $this->tertiaryHost,
                    $this->tertiaryApiKey,
                    'Spotify23'
                );

                if ($tertiary['success'] && isset($tertiary['data']['tracks']) && is_array($tertiary['data']['tracks'])) {
                    foreach ($tertiary['data']['tracks'] as $track) {
                        if ($track !== null && isset($track['id'])) {
                            $allResults[$track['id']] = [
                                'id' => $track['id'],
                                'name' => $track['name'] ?? null,
                                'popularity' => $track['popularity'] ?? 0,
                                'preview_url' => $track['preview_url'] ?? null,
                                'external_urls' => $track['external_urls'] ?? [],
                                'external_ids' => $track['external_ids'] ?? [],
                            ];
                        }
                    }
                }
            }
        }

        \Log::info('🎵 [RAPIDAPI SPOTIFY] Batch track details completed', [
            'requested' => count($ids),
            'returned' => count($allResults)
        ]);

        return $allResults;
    }

    /**
     * Batch get album details (including release_date and popularity) using RapidAPI Spotify
     * Similar to getBatchTracks but for albums
     *
     * @param array $albumIds Array of Spotify album IDs
     * @return array Map of album_id => ['id', 'name', 'release_date', 'popularity', ...]
     */
    public function getBatchAlbums(array $albumIds): array
    {
        \Log::info('💿 [RAPIDAPI SPOTIFY] Starting batch album details', [
            'album_count' => count($albumIds),
            'timestamp' => now()->toISOString()
        ]);

        $ids = array_values(array_filter(array_map('trim', $albumIds)));
        if (empty($ids)) {
            return [];
        }

        $allResults = [];

        // Make a single batch request with all album IDs comma-separated
        $idsString = implode(',', $ids);

        // Primary: spotify81
        $primary = $this->makeRequest(
            "/albums",
            ['ids' => $idsString],
            $this->primaryHost,
            $this->apiKey,
            'Spotify81'
        );

        if ($primary['success'] && isset($primary['data']['albums']) && is_array($primary['data']['albums'])) {
            foreach ($primary['data']['albums'] as $album) {
                if ($album !== null && isset($album['id'])) {
                    $allResults[$album['id']] = [
                        'id' => $album['id'],
                        'name' => $album['name'] ?? null,
                        'release_date' => $album['release_date'] ?? null,
                        'popularity' => $album['popularity'] ?? null,
                        'images' => $album['images'] ?? [],
                        'external_urls' => $album['external_urls'] ?? [],
                    ];
                }
            }
        } else {
            // Backup: spotify-web2
            \Log::info('💿 [RAPIDAPI SPOTIFY] Primary failed, trying backup', [
                'timestamp' => now()->toISOString()
            ]);

            $backup = $this->makeRequest(
                "/albums",
                ['ids' => $idsString],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backup['success'] && isset($backup['data']['albums']) && is_array($backup['data']['albums'])) {
                foreach ($backup['data']['albums'] as $album) {
                    if ($album !== null && isset($album['id'])) {
                        $allResults[$album['id']] = [
                            'id' => $album['id'],
                            'name' => $album['name'] ?? null,
                            'release_date' => $album['release_date'] ?? null,
                            'popularity' => $album['popularity'] ?? null,
                            'images' => $album['images'] ?? [],
                            'external_urls' => $album['external_urls'] ?? [],
                        ];
                    }
                }
            } else {
                // Tertiary: spotify23
                \Log::info('💿 [RAPIDAPI SPOTIFY] Backup failed, trying tertiary', [
                    'timestamp' => now()->toISOString()
                ]);

                $tertiary = $this->makeRequest(
                    "/albums",
                    ['ids' => $idsString],
                    $this->tertiaryHost,
                    $this->tertiaryApiKey,
                    'Spotify23'
                );

                if ($tertiary['success'] && isset($tertiary['data']['albums']) && is_array($tertiary['data']['albums'])) {
                    foreach ($tertiary['data']['albums'] as $album) {
                        if ($album !== null && isset($album['id'])) {
                            $allResults[$album['id']] = [
                                'id' => $album['id'],
                                'name' => $album['name'] ?? null,
                                'release_date' => $album['release_date'] ?? null,
                                'popularity' => $album['popularity'] ?? null,
                                'images' => $album['images'] ?? [],
                                'external_urls' => $album['external_urls'] ?? [],
                            ];
                        }
                    }
                }
            }
        }

        \Log::info('💿 [RAPIDAPI SPOTIFY] Batch album details completed', [
            'requested' => count($ids),
            'returned' => count($allResults)
        ]);

        return $allResults;
    }

    /**
     * Get artist preview tracks using RapidAPI Spotify
     * GET /artists/{artist_id}/albums then GET /albums/{album_id}/tracks
     */
    public function getArtistPreviewTracks(string $artistId, int $limit = 1): array
    {
        try {
            \Log::info('🎵 [RAPIDAPI SPOTIFY] Getting artist preview tracks via artist_overview', [
                'artist_id' => $artistId,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            // Get artist overview with popularReleases
            $overviewResult = $this->makeRequest(
                "/artist_overview",
                ['id' => $artistId],
                'spotify81.p.rapidapi.com',
                config('services.rapidapi.key'),
                'Spotify81'
            );

            // Check if we have topTracks data (preferred) or fall back to popularReleases
            $hasTopTracks = isset($overviewResult['data']['data']['artist']['discography']['topTracks']['items']);

            if ($hasTopTracks) {
                \Log::info('🎵 [RAPIDAPI SPOTIFY] Using topTracks for preview', [
                    'artist_id' => $artistId
                ]);

                $topTracks = $overviewResult['data']['data']['artist']['discography']['topTracks']['items'];

                if (empty($topTracks)) {
                    \Log::warning('🎵 [RAPIDAPI SPOTIFY] No top tracks found for artist', [
                        'artist_id' => $artistId
                    ]);
                    return [];
                }

                // Sort by playcount (highest first) and get multiple tracks based on limit
                usort($topTracks, function($a, $b) {
                    $aPlaycount = $a['track']['playcount'] ?? 0;
                    $bPlaycount = $b['track']['playcount'] ?? 0;
                    return $bPlaycount - $aPlaycount;
                });

                // Process multiple tracks up to the limit
                $tracks = [];
                $tracksToProcess = array_slice($topTracks, 0, $limit);

                foreach ($tracksToProcess as $trackItem) {
                    $topTrack = $trackItem['track'] ?? null;

                    if (!$topTrack) {
                        continue;
                    }

                    $trackId = $topTrack['id'] ?? null;
                    $trackName = $topTrack['name'] ?? 'Unknown Track';
                    $previewUrl = $topTrack['preview_url'] ?? null;
                    $artistName = $topTrack['artists']['items'][0]['profile']['name'] ?? 'Unknown';

                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Found top track', [
                        'track_id' => $trackId,
                        'track_name' => $trackName,
                        'playcount' => $topTrack['playcount'] ?? 0
                    ]);

                    if ($trackId) {
                        $tracks[] = [
                            'id' => $trackId,
                            'name' => $trackName,
                            'artists' => [['name' => $artistName]],
                            'preview_url' => $previewUrl,
                            'external_url' => "https://open.spotify.com/track/{$trackId}",
                            'duration_ms' => $topTrack['duration']['totalMilliseconds'] ?? null,
                            'embed_type' => 'track' // This is a real track, not an album
                        ];
                    }
                }

                if (!empty($tracks)) {
                    return $tracks;
                }
            }

            // Fallback to popularReleases if topTracks not available
            if (!isset($overviewResult['data']['data']['artist']['discography']['popularReleases']['items'])) {
                \Log::warning('🎵 [RAPIDAPI SPOTIFY] No popular releases or top tracks found for artist', [
                    'artist_id' => $artistId,
                    'success' => $overviewResult['success'] ?? false
                ]);
                return [];
            }

            \Log::info('🎵 [RAPIDAPI SPOTIFY] Falling back to popularReleases', [
                'artist_id' => $artistId
            ]);

            $popularReleases = $overviewResult['data']['data']['artist']['discography']['popularReleases']['items'];

            // Find first SINGLE type release
            $singleRelease = null;
            $parentItem = null;
            foreach ($popularReleases as $item) {
                if (isset($item['releases']['items'][0])) {
                    $release = $item['releases']['items'][0];
                    if (isset($release['type']) && strtoupper($release['type']) === 'SINGLE') {
                        $singleRelease = $release;
                        $parentItem = $item; // Store parent item which may contain track data
                        break;
                    }
                }
            }

            if (!$singleRelease) {
                \Log::warning('🎵 [RAPIDAPI SPOTIFY] No SINGLE type found in popularReleases', [
                    'artist_id' => $artistId,
                    'releases_count' => count($popularReleases)
                ]);
                return [];
            }

            \Log::info('🎵 [RAPIDAPI SPOTIFY] Single release structure', [
                'release_keys' => array_keys($singleRelease),
                'parent_item_keys' => $parentItem ? array_keys($parentItem) : [],
                'has_tracks_in_release' => isset($singleRelease['tracks']),
                'has_tracks_in_parent' => $parentItem && isset($parentItem['tracks']),
                'tracks_structure' => isset($singleRelease['tracks']) ? array_keys($singleRelease['tracks']) : null,
                'tracks_data_sample' => isset($singleRelease['tracks']) ? json_encode($singleRelease['tracks']) : null
            ]);

            // Extract track info from the single release
            $shareUrl = $singleRelease['sharingInfo']['shareUrl'] ?? null;
            $albumId = $singleRelease['id'] ?? null;
            $trackId = null;
            $trackName = null;
            $previewUrl = null;

            // The tracks are already embedded in the release data - use them directly!
            if (isset($singleRelease['tracks']['items'][0])) {
                $firstTrack = $singleRelease['tracks']['items'][0];

                // Extract track ID from the embedded track data
                if (isset($firstTrack['track']['id'])) {
                    $trackId = $firstTrack['track']['id'];
                    $trackName = $firstTrack['track']['name'] ?? 'Unknown Track';
                    $previewUrl = $firstTrack['track']['preview_url'] ?? null;
                } elseif (isset($firstTrack['id'])) {
                    $trackId = $firstTrack['id'];
                    $trackName = $firstTrack['name'] ?? 'Unknown Track';
                    $previewUrl = $firstTrack['preview_url'] ?? null;
                }

                // If no direct ID, try to extract from URI
                if (!$trackId) {
                    $uri = $firstTrack['track']['uri'] ?? $firstTrack['uri'] ?? null;
                    if ($uri && preg_match('/spotify:track:([a-zA-Z0-9]+)/', $uri, $matches)) {
                        $trackId = $matches[1];
                    }
                }

                \Log::info('🎵 [RAPIDAPI SPOTIFY] Extracted track from embedded tracks', [
                    'track_id' => $trackId,
                    'track_name' => $trackName,
                    'has_preview' => !empty($previewUrl),
                    'track_structure' => array_keys($firstTrack)
                ]);
            }

            // Fallback to release name if no track name found
            $trackName = $trackName ?: ($singleRelease['name'] ?? 'Unknown Track');

            // For albums where we can't get individual track IDs, use the album ID for embedding
            // Spotify album embeds work for all albums and will show the first track by default
            $trackCount = $singleRelease['tracks']['totalCount'] ?? 0;
            if (!$trackId && $albumId && $trackCount > 0) {
                $trackId = $albumId; // Use album ID for album embed
                \Log::info('🎵 [RAPIDAPI SPOTIFY] Using album ID for album embed', [
                    'album_id' => $albumId,
                    'track_count' => $trackCount
                ]);
            }

            \Log::info('🎵 [RAPIDAPI SPOTIFY] Found SINGLE release', [
                'artist_id' => $artistId,
                'track_name' => $trackName,
                'track_id' => $trackId,
                'album_id' => $albumId,
                'track_count' => $trackCount,
                'using_album_as_track' => $trackId === $albumId
            ]);

            // If we already have a track ID (or using album ID for single), use it directly
            if ($trackId) {
                $isAlbumId = ($trackId === $albumId);
                $embedType = $isAlbumId ? 'album' : 'track';

                \Log::info('🎵 [RAPIDAPI SPOTIFY] Using ID for embed', [
                    'id' => $trackId,
                    'is_album_id' => $isAlbumId,
                    'embed_type' => $embedType,
                    'skipping_album_tracks_api_call' => true
                ]);

                return [
                    [
                        'id' => $trackId,
                        'name' => $trackName,
                        'artists' => [['name' => $singleRelease['artists'][0]['profile']['name'] ?? 'Unknown']],
                        'preview_url' => $previewUrl,
                        'external_url' => "https://open.spotify.com/{$embedType}/{$trackId}",
                        'duration_ms' => null,
                        'embed_type' => $embedType // Add this so frontend knows whether to use /track/ or /album/
                    ]
                ];
            }

            // Only fetch tracks from album API if we don't have embedded track data
            if ($albumId) {
                \Log::info('🎵 [RAPIDAPI SPOTIFY] No embedded track ID, fetching from albums API', [
                    'album_id' => $albumId
                ]);

                // Primary: spotify81
                $tracksResult = $this->makeRequest(
                    "/albums/{$albumId}/tracks",
                    ['limit' => max(1, $limit)],
                    'spotify81.p.rapidapi.com',
                    config('services.rapidapi.key'),
                    'Spotify81'
                );

                if ($tracksResult['success'] && !empty($tracksResult['data']['items'])) {
                    $tracks = $this->formatTracksArray($tracksResult['data']['items']);
                    return array_slice($tracks, 0, $limit);
                }

                // Backup: spotify-web2
                $backupTracks = $this->makeRequest(
                    "/albums/{$albumId}/tracks",
                    ['limit' => max(1, $limit)],
                    'spotify-web2.p.rapidapi.com',
                    config('services.rapidapi.key'),
                    'SpotifyWeb2'
                );

                if ($backupTracks['success'] && !empty($backupTracks['data']['items'])) {
                    $tracks = $this->formatTracksArray($backupTracks['data']['items']);
                    return array_slice($tracks, 0, $limit);
                }
            }

            // Fallback: return a shell entry with track ID if we have it, otherwise album share url
            \Log::info('🎵 [RAPIDAPI SPOTIFY] Using fallback track data', [
                'has_track_id' => !empty($trackId),
                'track_id' => $trackId,
                'album_id' => $albumId
            ]);

            return [
                [
                    'id' => $trackId, // Use track ID if available, otherwise null
                    'name' => $trackName,
                    'artists' => [['name' => $singleRelease['artists'][0]['name'] ?? 'Unknown']],
                    'share_url' => $shareUrl,
                    'album_id' => $albumId,
                    'preview_url' => null,
                    'external_url' => $trackId ? "https://open.spotify.com/track/{$trackId}" : $shareUrl,
                    'label' => $singleRelease['label'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            \Log::error('🎵 [RAPIDAPI SPOTIFY] Artist preview tracks failed', [
                'artist_id' => $artistId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get track popularity using RapidAPI Spotify
     * GET /tracks/{track_id}
     */
    public function getTrackPopularity(string $trackId): array
    {
        try {
            $result = $this->makeRequest(
                "/tracks/{$trackId}",
                [],
                'spotify81.p.rapidapi.com',
                config('services.rapidapi.key'),
                'Spotify81'
            );

            if ($result['success'] && isset($result['data'])) {
                return [
                    'id' => $result['data']['id'] ?? null,
                    'name' => $result['data']['name'] ?? null,
                    'popularity' => $result['data']['popularity'] ?? 0,
                    'preview_url' => $result['data']['preview_url'] ?? null,
                    'external_url' => $result['data']['external_urls']['spotify'] ?? null
                ];
            }

            return [];
        } catch (\Exception $e) {
            \Log::error('RapidAPI Spotify track popularity failed', [
                'track_id' => $trackId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format artists array from Spotify API response
     */
    private function formatArtistsArray(array $artists): array
    {
        return array_map(function ($artist) {
            // Handle RapidAPI Spotify81 specific structure with data wrapper
            if (isset($artist['data'])) {
                $data = $artist['data'];
                $profile = $data['profile'] ?? [];
                $visuals = $data['visuals'] ?? [];
                
                // Extract Spotify ID from URI (spotify:artist:7dGJo4pcD2V6oG8kP0tJRR)
                $spotifyId = null;
                if (isset($data['uri']) && strpos($data['uri'], 'spotify:artist:') === 0) {
                    $spotifyId = str_replace('spotify:artist:', '', $data['uri']);
                } elseif (isset($data['id'])) {
                    $spotifyId = $data['id'];
                }
                
                // Extract images from visuals
                $images = [];
                if (isset($visuals['avatarImage']['sources'])) {
                    $images = $visuals['avatarImage']['sources'];
                }
                
                return [
                    'id' => $spotifyId,
                    'name' => $profile['name'] ?? 'Unknown Artist',
                    'followers' => $profile['followers'] ?? 0,
                    'popularity' => $profile['popularity'] ?? 0,
                    'genres' => $profile['genres'] ?? [],
                    'external_url' => $profile['external_urls']['spotify'] ?? null,
                    'images' => $images
                ];
            }
            
            // Handle RapidAPI structure with profile directly (no data wrapper)
            if (isset($artist['profile'])) {
                $profile = $artist['profile'];
                $visuals = $artist['visuals'] ?? [];
                
                // Extract Spotify ID from URI or id field
                $spotifyId = null;
                if (isset($artist['uri']) && strpos($artist['uri'], 'spotify:artist:') === 0) {
                    $spotifyId = str_replace('spotify:artist:', '', $artist['uri']);
                } elseif (isset($artist['id'])) {
                    $spotifyId = $artist['id'];
                }
                
                // Extract images from visuals
                $images = [];
                if (isset($visuals['avatarImage']['sources'])) {
                    $images = $visuals['avatarImage']['sources'];
                }
                
                return [
                    'id' => $spotifyId,
                    'name' => $profile['name'] ?? 'Unknown Artist',
                    'followers' => $profile['followers'] ?? 0,
                    'popularity' => $profile['popularity'] ?? 0,
                    'genres' => $profile['genres'] ?? [],
                    'external_url' => $profile['external_urls']['spotify'] ?? null,
                    'images' => $images
                ];
            }
            
            // Fallback to standard Spotify Web API structure
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
     * Format tracks array from Spotify API response
     */
    private function formatTracksArray(array $tracks): array
    {
        return array_map(function ($track) {
            return [
                'id' => $track['id'] ?? null,
                'name' => $track['name'] ?? 'Unknown Track',
                'artists' => array_map(function ($artist) {
                    return ['name' => $artist['name'] ?? 'Unknown Artist'];
                }, $track['artists'] ?? []),
                'preview_url' => $track['preview_url'] ?? null,
                'external_url' => $track['external_urls']['spotify'] ?? null,
                'duration_ms' => $track['duration_ms'] ?? null
            ];
        }, $tracks);
    }

    /**
     * Normalize albums array to a common Spotify-like structure
     */
    private function formatAlbumsArray(array $albums): array
    {
        return array_map(function ($album) {
            // RapidAPI structure wrapped in data
            if (isset($album['data'])) {
                $data = $album['data'];
                $uri = $data['uri'] ?? null;
                $spotifyId = null;
                if ($uri && strpos($uri, 'spotify:album:') === 0) {
                    $spotifyId = str_replace('spotify:album:', '', $uri);
                } elseif (isset($data['id'])) {
                    $spotifyId = $data['id'];
                }

                // Images
                $images = [];
                if (isset($data['coverArt']['sources']) && is_array($data['coverArt']['sources'])) {
                    // Normalize keys to match Spotify (url/height/width)
                    foreach ($data['coverArt']['sources'] as $img) {
                        $images[] = [
                            'url' => $img['url'] ?? null,
                            'height' => $img['height'] ?? null,
                            'width' => $img['width'] ?? null,
                        ];
                    }
                } elseif (isset($data['images'])) {
                    $images = $data['images'];
                }

                // Release date
                $releaseDate = null;
                if (isset($data['date']['year'])) {
                    $releaseDate = (string) $data['date']['year'];
                } elseif (isset($data['release_date'])) {
                    $releaseDate = $data['release_date'];
                }

                // External URL
                $externalUrl = null;
                if ($spotifyId) {
                    $externalUrl = "https://open.spotify.com/album/{$spotifyId}";
                } elseif (isset($data['external_urls']['spotify'])) {
                    $externalUrl = $data['external_urls']['spotify'];
                }

                return [
                    'id' => $spotifyId,
                    'name' => $data['name'] ?? null,
                    'uri' => $uri,
                    'release_date' => $releaseDate,
                    'images' => $images,
                    'external_urls' => ['spotify' => $externalUrl],
                    'label' => $data['label'] ?? null,
                    'artists' => $data['artists']['items'] ?? ($data['artists'] ?? []),
                ];
            }

            // Already Spotify-like
            return $album;
        }, $albums);
    }

    /**
     * Normalize string for comparison
     * Lowercase, trim, remove special characters
     */
    private function normalizeString(string $str): string
    {
        $normalized = strtolower(trim($str));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Summarize array structure for logging (recursive, limited depth)
     */
    private function summarizeArrayStructure(array $data, int $maxDepth = 3, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['...' => '(max depth reached)'];
        }

        $summary = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) > 10) {
                    $summary[$key] = ['type' => 'array', 'count' => count($value), 'sample_keys' => array_slice(array_keys($value), 0, 5)];
                } else {
                    $summary[$key] = $this->summarizeArrayStructure($value, $maxDepth, $currentDepth + 1);
                }
            } else {
                $summary[$key] = is_string($value) && strlen($value) > 50 
                    ? substr($value, 0, 50) . '...' 
                    : $value;
            }
        }
        return $summary;
    }
}
