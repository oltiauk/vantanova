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
    private string $apiKey;
    private string $backupApiKey;
    private float $rateLimitDelay = 0.2; // 5 req/sec = 200ms delay

    public function __construct()
    {
        // Primary provider: spotify81
        $this->primaryHost = config('services.rapidapi_spotify.primary_host', 'spotify81.p.rapidapi.com');
        $this->apiKey = config('services.rapidapi_spotify.primary_key', config('services.rapidapi_spotify.key'));

        // Backup provider: spotify-web2
        $this->backupHost = config('services.rapidapi_spotify.backup_host', 'spotify-web2.p.rapidapi.com');
        $this->backupApiKey = config('services.rapidapi_spotify.backup_key', $this->apiKey);
    }

    public static function enabled(): bool
    {
        return !empty(config('services.rapidapi_spotify.key')) ||
               !empty(config('services.rapidapi_spotify.primary_key')) ||
               !empty(config('services.rapidapi.key'));
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
            // Try primary API first
            $result = $this->makeRequest(
                '/search',
                ['q' => $query, 'type' => $type, 'limit' => $limit],
                $this->primaryHost,
                $this->apiKey,
                'spotify81'
            );

            if ($result['success']) {
                return $result;
            }

            // If primary failed with rate limit or error, try backup
            Log::warning('Primary RapidAPI failed, trying backup', [
                'error' => $result['error'] ?? 'Unknown error'
            ]);

            return $this->makeRequest(
                '/search',
                ['q' => $query, 'type' => $type, 'limit' => $limit],
                $this->backupHost,
                $this->backupApiKey,
                'spotify-web2'
            );

        } catch (\Exception $e) {
            Log::error('RapidAPI Spotify search failed completely', [
                'message' => $e->getMessage()
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
        $url = "https://{$host}{$endpoint}";

        $headers = [
            'X-RapidAPI-Key' => $apiKey,
            'X-RapidAPI-Host' => $host,
        ];

        try {
            Log::info("🔥 RAPIDAPI_REQUEST_{$provider}", [
                'endpoint' => $endpoint,
                'params' => $params,
                'timestamp' => now()->toISOString()
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($url, $params);

            // Rate limiting delay
            usleep($this->rateLimitDelay * 1000000);

            Log::info("🔥 RAPIDAPI_RESPONSE_{$provider}", [
                'status' => $response->status(),
                'success' => $response->successful(),
                'timestamp' => now()->toISOString()
            ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            // Check for rate limit (429)
            if ($response->status() === 429) {
                Log::warning("Rate limit exceeded on {$provider}", [
                    'endpoint' => $endpoint
                ]);
                return ['success' => false, 'error' => 'Rate limit exceeded', 'status' => 429];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("RapidAPI request exception ({$provider})", [
                'error' => $e->getMessage()
            ]);
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
        // Handle the actual RapidAPI response structure: data.tracks[] where each item has a nested 'data' object
        if (!isset($searchResults['data']['tracks']) || !is_array($searchResults['data']['tracks'])) {
            Log::warning("🔥 RapidAPI: No tracks in search results", [
                'expected_path' => 'data.tracks',
                'available_keys' => array_keys($searchResults['data'] ?? $searchResults)
            ]);
            return null;
        }

        $cleanedExpectedArtist = $this->normalizeString($artistName);
        $cleanedExpectedTitle = $this->normalizeString($trackTitle);

        Log::info("🔥 RapidAPI: Looking for exact match", [
            'expected_artist' => $artistName,
            'expected_title' => $trackTitle,
            'normalized_artist' => $cleanedExpectedArtist,
            'normalized_title' => $cleanedExpectedTitle,
            'total_results' => count($searchResults['data']['tracks'])
        ]);

        foreach ($searchResults['data']['tracks'] as $index => $trackWrapper) {
            // Each track is wrapped in a 'data' object
            $track = $trackWrapper['data'] ?? $trackWrapper;

            // Artist name is in artists.items[0].profile.name
            $trackArtist = $track['artists']['items'][0]['profile']['name'] ?? '';
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
