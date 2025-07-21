<?php

namespace App\Http\Integrations\YouTube\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchVideosByQueryRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $query, private readonly string $pageToken = '')
    {
    }

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /** @inheritdoc */
    protected function defaultQuery(): array
    {
        return [
            'part' => 'snippet',
            'type' => 'video',
            'maxResults' => 10,
            'pageToken' => $this->pageToken,
            'q' => $this->query,
        ];
    }
}