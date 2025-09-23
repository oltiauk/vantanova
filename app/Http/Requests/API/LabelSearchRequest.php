<?php

namespace App\Http\Requests\API;

use App\Http\Requests\API\Request;

class LabelSearchRequest extends Request
{
    public function rules(): array
    {
        return [
            'label' => 'required|string|max:200',
            'hipster' => 'sometimes|boolean',
            'release_date' => 'sometimes|string|in:1w,1m,3m,6m,1y,2y,5y',
        ];
    }
}