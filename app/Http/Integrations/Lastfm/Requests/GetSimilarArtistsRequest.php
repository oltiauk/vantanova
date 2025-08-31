<?php

namespace App\Http\Integrations\Lastfm\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSimilarArtistsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private string $artistMbid)
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
            'method' => 'artist.getSimilar',
            'mbid' => $this->artistMbid,
            'format' => 'json',
            'limit' => 100, // Get maximum similar artists
        ];
    }

    /** @return array<mixed>|null */
    public function createDtoFromResponse(Response $response): ?array
    {
        $data = $response->object();
        
        if (!$data || !isset($data->similarartists->artist)) {
            return [];
        }

        $artists = $data->similarartists->artist;
        
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
                'match' => $artist->match ?? '0',
                'listeners' => null, // Not included in getSimilar response
            ];
        }, $artists);
    }
}