<?php

namespace App\Http\Integrations\Lastfm\Requests;

use App\Http\Integrations\Lastfm\Concerns\FormatsLastFmText;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetArtistInfoByMbidRequest extends Request
{
    use FormatsLastFmText;

    protected Method $method = Method::GET;

    public function __construct(private readonly string $artistMbid)
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
            'method' => 'artist.getInfo',
            'mbid' => $this->artistMbid,
            'format' => 'json',
        ];
    }

    /** @return array<mixed>|null */
    public function createDtoFromResponse(Response $response): ?array
    {
        $data = $response->object();
        
        if (!$data || !isset($data->artist)) {
            return null;
        }

        $artist = $data->artist;

        return [
            'name' => $artist->name ?? '',
            'mbid' => $artist->mbid ?? '',
            'url' => $artist->url ?? '',
            'listeners' => $artist->stats->listeners ?? null,
            'playcount' => $artist->stats->playcount ?? null,
            'image' => $artist->image ?? [],
            'bio' => [
                'summary' => self::formatLastFmText($artist->bio->summary ?? ''),
                'content' => self::formatLastFmText($artist->bio->content ?? ''),
            ],
        ];
    }
}