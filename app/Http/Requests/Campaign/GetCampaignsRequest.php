<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\FilterableRequest;

class GetCampaignsRequest extends FilterableRequest
{
    /**
     * Fields that are allowed to be filtered
     */
    protected array $allowedFields = [
        'coordination_id',
        'created_at'
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // Add any additional validation rules specific to Channel here
        ]);
    }
}
