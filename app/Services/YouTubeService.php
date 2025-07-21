<?php

namespace App\Services;

use App\Http\Integrations\YouTube\Requests\SearchVideosRequest;
use App\Http\Integrations\YouTube\Requests\SearchVideosByQueryRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use App\Models\Song;
use App\Services\YouTubeScrapingService;
use Illuminate\Support\Facades\Cache;
use Throwable;

class YouTubeService
{
    public function __construct(private readonly YouTubeConnector $connector)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('koel.services.youtube.key');
    }

    public function searchVideosRelatedToSong(Song $song, string $pageToken = ''): ?object
    {
        if (!self::enabled()) {
            return null;
        }

        $request = new SearchVideosRequest($song, $pageToken);

        try {
            return Cache::remember(
                cache_key('YouTube search query', serialize($request->query()->all())),
                now()->addWeek(),
                fn () => $this->connector->send($request)->object()
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function searchVideosByQuery(string $query, string $pageToken = ''): ?object
    {
        // If API key is available, use the API method
        if (self::enabled()) {
            $request = new SearchVideosByQueryRequest($query, $pageToken);

            try {
                return Cache::remember(
                    cache_key('YouTube search query', $query, $pageToken),
                    now()->addWeek(),
                    fn () => $this->connector->send($request)->object()
                );
            } catch (Throwable) {
                // Fall back to scraping if API fails
                return $this->searchVideosByQueryViaScraping($query);
            }
        }

        // Use scraping method when no API key is available
        return $this->searchVideosByQueryViaScraping($query);
    }

    private function searchVideosByQueryViaScraping(string $query): ?object
    {
        $scrapingService = new YouTubeScrapingService();
        $results = $scrapingService->searchVideosByQuery($query);
        
        // Format results to match YouTube API response structure
        return (object) [
            'items' => $results,
            'nextPageToken' => null
        ];
    }
}
