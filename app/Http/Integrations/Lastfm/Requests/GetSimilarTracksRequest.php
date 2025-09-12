<?php

namespace App\Http\Integrations\Lastfm\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSimilarTracksRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private string $artist, private string $track, private int $limit = 50)
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
            'method' => 'track.getSimilar',
            'artist' => $this->artist,
            'track' => $this->track,
            'format' => 'json',
            'limit' => $this->limit,
            'autocorrect' => 1, // Enable auto-correction of misspelled names
        ];
    }

    /** @return array<mixed>|null */
    public function createDtoFromResponse(Response $response): ?array
    {
        $data = $response->object();
        
        if (!$data || !isset($data->similartracks->track)) {
            return [];
        }

        $tracks = $data->similartracks->track;
        
        // Handle single track result (not an array)
        if (!is_array($tracks)) {
            $tracks = [$tracks];
        }

        return array_map(function ($track) {
            // Convert image array to proper format
            $images = [];
            if (isset($track->image) && is_array($track->image)) {
                $images = array_map(function ($img) {
                    return [
                        'size' => $img->size ?? '',
                        '#text' => $img->{'#text'} ?? ''
                    ];
                }, $track->image);
            }

            $matchScore = $track->match ?? '0';
            
            $result = [
                'title' => $track->name ?? '',
                'artist' => [
                    'name' => $track->artist->name ?? '',
                    'mbid' => $track->artist->mbid ?? '',
                    'url' => $track->artist->url ?? '',
                ],
                'mbid' => $track->mbid ?? '',
                'url' => $track->url ?? '',
                'image' => $images,
                'match' => $matchScore,
                'streamable' => $track->streamable ?? '0',
                'source' => 'lastfm',
            ];
            
            \Log::info('LASTFM_TRACK_PROCESSED', [
                'track' => $track->name ?? 'Unknown',
                'artist' => $track->artist->name ?? 'Unknown',
                'raw_match' => $track->match ?? 'not_set',
                'processed_match' => $matchScore,
                'result_match' => $result['match']
            ]);
            
            return $result;
        }, $tracks);
    }
}