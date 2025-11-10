<?php

namespace App\Http\Requests\API;

use App\Http\Requests\API\Request;

class GenreByYearRequest extends Request
{
    public function rules(): array
    {
        return [
            'genre' => 'required|string|max:200',
            'year' => 'sometimes|string|digits:4',
            'popularity_min' => 'sometimes|integer|min:0|max:100',
            'popularity_max' => 'sometimes|integer|min:0|max:100',
            'followers_min' => 'sometimes|integer|min:0',
            'followers_max' => 'sometimes|integer|min:0',
            'offset' => 'sometimes|integer|min:0',
        ];
    }
}

