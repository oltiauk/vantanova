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
     * Search for tracks with primary/backup fallback
     *
     * @param string $query Search query (artist + track name)
     * @param int $limit Number of results (default: 10)
     * @param string $type Type of search (default: 'tracks')
     * @return array
     */
    public function searchTracks(string $query, int $limit = 20, string $type = 'tracks'): array
    {
        try {
            \Log::info('🔍 [RAPIDAPI SPOTIFY] Starting track search with 3-tier backup', [
                'query' => $query,
                'type' => $type,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            // Try primary API first (spotify81)
            $result = $this->makeRequest(
                '/search',
                ['q' => $query, 'type' => $type, 'limit' => $limit],
                $this->primaryHost,
                $this->apiKey,
                'Spotify81'
            );

            if ($result['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Primary search successful', [
                    'provider' => 'Spotify81',
                    'query' => $query
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
                ['q' => $query, 'type' => $type, 'limit' => $limit],
                $this->backupHost,
                $this->backupApiKey,
                'SpotifyWeb2'
            );

            if ($backupResult['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Backup search successful', [
                    'provider' => 'SpotifyWeb2',
                    'query' => $query
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
                ['q' => $query, 'type' => $type, 'limit' => $limit],
                $this->tertiaryHost,
                $this->tertiaryApiKey,
                'Spotify23'
            );

            if ($tertiaryResult['success']) {
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Tertiary search successful', [
                    'provider' => 'Spotify23',
                    'query' => $query
                ]);
                return $tertiaryResult;
            }

            // All APIs failed
            \Log::error('🔍 [RAPIDAPI SPOTIFY] All search APIs failed', [
                'query' => $query,
                'primary_error' => $result['error'] ?? 'Unknown',
                'backup_error' => $backupResult['error'] ?? 'Unknown',
                'tertiary_error' => $tertiaryResult['error'] ?? 'Unknown'
            ]);

            return ['success' => false, 'error' => 'All search APIs failed'];

        } catch (\Exception $e) {
            \Log::error('🔍 [RAPIDAPI SPOTIFY] Search failed with exception', [
                'query' => $query,
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
        // Circuit Breaker: Check if this provider should be skipped
        if ($this->shouldSkipProvider($provider)) {
            return [
                'success' => false,
                'error' => 'Provider temporarily disabled (circuit breaker)',
                'circuit_open' => true
            ];
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
                $this->recordSuccess($provider);
                return ['success' => true, 'data' => $response->json()];
            }

            // Check for rate limit (429)
            if ($response->status() === 429) {
                Log::warning("Rate limit exceeded on {$provider}", [
                    'endpoint' => $endpoint
                ]);
                // Circuit Breaker: Record failure
                $this->recordFailure($provider);
                return ['success' => false, 'error' => 'Rate limit exceeded', 'status' => 429];
            }

            // Other errors (4xx, 5xx)
            // Circuit Breaker: Record failure
            $this->recordFailure($provider);
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
            $this->recordFailure($provider);
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
            \Log::info('🔍 [RAPIDAPI SPOTIFY] Starting artist search', [
                'query' => $query,
                'limit' => $limit,
                'timestamp' => now()->toISOString()
            ]);

            $params = [
                'q' => $query,
                'type' => 'artists',
                'limit' => min($limit, 50) // API limit
            ];

            $result = $this->makeRequest(
                '/search',
                $params,
                'spotify81.p.rapidapi.com',
                config('services.rapidapi.key'),
                'Spotify81'
            );

            if ($result['success'] && isset($result['data']['artists']['items'])) {
                $artists = $this->formatArtistsArray($result['data']['artists']['items']);
                \Log::info('🔍 [RAPIDAPI SPOTIFY] Artist search successful', [
                    'query' => $query,
                    'found_count' => count($artists),
                    'sample_artists' => array_slice(array_map(fn($a) => $a['name'], $artists), 0, 3)
                ]);
                return $artists;
            }

            \Log::warning('🔍 [RAPIDAPI SPOTIFY] Artist search returned no results', [
                'query' => $query,
                'result_success' => $result['success'] ?? false,
                'has_artists' => isset($result['data']['artists']['items'])
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

            if ($result['success'] && isset($result['data']['artists'])) {
                // Detect structure and extract artists array
                $isOfficialStructure = isset($result['data']['artists']['items']);
                $artistsData = $isOfficialStructure
                    ? ($result['data']['artists']['items'] ?? [])
                    : ($result['data']['artists'] ?? []);

                if (!empty($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Primary similar artists successful', [
                        'provider' => 'Spotify81',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi'
                    ]);
                    return $artists;
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

            if ($backupResult['success'] && isset($backupResult['data']['artists'])) {
                // Detect structure and extract artists array
                $isOfficialStructure = isset($backupResult['data']['artists']['items']);
                $artistsData = $isOfficialStructure
                    ? ($backupResult['data']['artists']['items'] ?? [])
                    : ($backupResult['data']['artists'] ?? []);

                if (!empty($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Backup similar artists successful', [
                        'provider' => 'SpotifyWeb2',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi'
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

            if ($tertiaryResult['success'] && isset($tertiaryResult['data']['artists'])) {
                // Detect structure and extract artists array
                $isOfficialStructure = isset($tertiaryResult['data']['artists']['items']);
                $artistsData = $isOfficialStructure
                    ? ($tertiaryResult['data']['artists']['items'] ?? [])
                    : ($tertiaryResult['data']['artists'] ?? []);

                if (!empty($artistsData)) {
                    $artists = $this->formatArtistsArray($artistsData);
                    \Log::info('🎵 [RAPIDAPI SPOTIFY] Tertiary similar artists successful', [
                        'provider' => 'Spotify23',
                        'artist_id' => $artistId,
                        'found_count' => count($artists),
                        'structure' => $isOfficialStructure ? 'official_spotify' : 'rapidapi'
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
            // Handle RapidAPI Spotify81 specific structure
            if (isset($artist['data'])) {
                $data = $artist['data'];
                $profile = $data['profile'] ?? [];
                $visuals = $data['visuals'] ?? [];
                
                // Extract Spotify ID from URI (spotify:artist:7dGJo4pcD2V6oG8kP0tJRR)
                $spotifyId = null;
                if (isset($data['uri']) && strpos($data['uri'], 'spotify:artist:') === 0) {
                    $spotifyId = str_replace('spotify:artist:', '', $data['uri']);
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
}
