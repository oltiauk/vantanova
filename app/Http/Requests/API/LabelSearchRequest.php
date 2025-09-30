<?php

namespace App\Http\Requests\API;

use App\Http\Requests\API\Request;

class LabelSearchRequest extends Request
{
    public function rules(): array
    {
        return [
            'label' => 'required|string|max:200',
            'new' => 'sometimes|in:true,false,1,0',
            'hipster' => 'sometimes|in:true,false,1,0', 
            'release_year' => 'sometimes|string|digits:4',
        ];
    }

    /**
     * Get the validated data from the request.
     * Convert string boolean values to actual booleans.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        if ($key === null) {
            // Convert string booleans to actual booleans for all data
            if (isset($validated['new'])) {
                $validated['new'] = in_array($validated['new'], ['true', '1'], true);
            }
            if (isset($validated['hipster'])) {
                $validated['hipster'] = in_array($validated['hipster'], ['true', '1'], true);
            }
            return $validated;
        }
        
        if (in_array($key, ['new', 'hipster']) && $validated !== $default) {
            return in_array($validated, ['true', '1'], true);
        }
        
        return $validated;
    }
}