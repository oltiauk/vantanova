<?php

namespace App\Http\Integrations\Lastfm\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class SearchArtistsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private string $searchQuery)
    {
    }

    public function resolveEndpoint(): string
    {
        return '/';
    }

    /** @return array<mixed> */
    protected function defaultQuery(): array
    {
        return [
            'method' => 'artist.search',
            'artist' => $this->searchQuery,
            'format' => 'json',
            'limit' => 30,
        ];
    }

    /** @return array<mixed>|null */
    public function createDtoFromResponse(Response $response): ?array
    {
        $data = $response->object();
        
        if (!$data || !isset($data->results->artistmatches->artist)) {
            return [];
        }

        $artists = $data->results->artistmatches->artist;
        
        // Handle single artist result (not an array)
        if (!is_array($artists)) {
            $artists = [$artists];
        }

        return array_map(function ($artist) {
            return [
                'name' => $artist->name ?? '',
                'mbid' => $artist->mbid ?? '',
                'url' => $artist->url ?? '',
                'image' => $artist->image ?? [],
                'listeners' => $artist->listeners ?? null,
            ];
        }, $artists);
    }
}