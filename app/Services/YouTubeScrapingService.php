<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Exception;

class YouTubeScrapingService
{
    public function searchVideosByQuery(string $query, int $maxResults = 10): array
    {
        return Cache::remember(
            "youtube_scrape_" . md5($query),
            now()->addHours(1), // Cache for 1 hour
            function() use ($query, $maxResults) {
                return $this->scrapeYouTubeSearch($query, $maxResults);
            }
        );
    }

    private function scrapeYouTubeSearch(string $query, int $maxResults): array
    {
        try {
            $searchUrl = 'https://www.youtube.com/results?search_query=' . urlencode($query);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get($searchUrl);

            if (!$response->successful()) {
                return [];
            }

            $html = $response->body();
            return $this->parseSearchResults($html, $maxResults);
            
        } catch (Exception $e) {
            \Log::error('YouTube scraping failed: ' . $e->getMessage());
            return [];
        }
    }

    private function parseSearchResults(string $html, int $maxResults): array
    {
        $results = [];
        
        // Look for the ytInitialData JavaScript object
        if (preg_match('/var ytInitialData = ({.*?});/', $html, $matches)) {
            $data = json_decode($matches[1], true);
            
            if (isset($data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'])) {
                $contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'];
                
                foreach ($contents as $content) {
                    if (isset($content['itemSectionRenderer']['contents'])) {
                        foreach ($content['itemSectionRenderer']['contents'] as $item) {
                            if (isset($item['videoRenderer']) && count($results) < $maxResults) {
                                $video = $item['videoRenderer'];
                                $results[] = $this->formatVideoResult($video);
                            }
                        }
                    }
                }
            }
        }
        
        // Fallback: Extract video IDs from direct pattern matching
        if (empty($results)) {
            $results = $this->extractVideoIdsFallback($html, $maxResults);
        }
        
        return $results;
    }

    private function formatVideoResult(array $video): array
    {
        return [
            'id' => [
                'videoId' => $video['videoId'] ?? null
            ],
            'snippet' => [
                'title' => $video['title']['runs'][0]['text'] ?? $video['title']['simpleText'] ?? 'Unknown Title',
                'description' => $video['descriptionSnippet']['runs'][0]['text'] ?? '',
                'thumbnails' => [
                    'medium' => [
                        'url' => $video['thumbnail']['thumbnails'][0]['url'] ?? ''
                    ]
                ]
            ]
        ];
    }

    private function extractVideoIdsFallback(string $html, int $maxResults): array
    {
        $results = [];
        
        // Pattern to match video IDs in the HTML
        if (preg_match_all('/\/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $matches)) {
            $videoIds = array_unique($matches[1]);
            
            foreach (array_slice($videoIds, 0, $maxResults) as $videoId) {
                $results[] = [
                    'id' => [
                        'videoId' => $videoId
                    ],
                    'snippet' => [
                        'title' => 'Video ' . $videoId,
                        'description' => '',
                        'thumbnails' => [
                            'medium' => [
                                'url' => "https://i.ytimg.com/vi/{$videoId}/mqdefault.jpg"
                            ]
                        ]
                    ]
                ];
            }
        }
        
        return $results;
    }
}