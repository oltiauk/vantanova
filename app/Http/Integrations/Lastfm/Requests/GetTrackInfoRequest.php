<?php

namespace App\Http\Integrations\Lastfm\Requests;

use App\Http\Integrations\Lastfm\Concerns\FormatsLastFmText;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetTrackInfoRequest extends Request
{
    use FormatsLastFmText;

    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $artist,
        private readonly string $track
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/';
    }

    /** @return array<mixed> */
    protected function defaultQuery(): array
    {
        return [
            'method' => 'track.getInfo',
            'artist' => $this->artist,
            'track' => $this->track,
            'autocorrect' => 1,
            'format' => 'json',
        ];
    }

    public function createDtoFromResponse(Response $response): ?array
    {
        $track = object_get($response->object(), 'track');

        if (!$track) {
            return null;
        }

        return [
            'artist' => object_get($track, 'artist.name'),
            'name' => object_get($track, 'name'),
            'playcount' => (int) object_get($track, 'playcount', 0),
            'listeners' => (int) object_get($track, 'listeners', 0),
            'url' => object_get($track, 'url'),
        ];
    }
}