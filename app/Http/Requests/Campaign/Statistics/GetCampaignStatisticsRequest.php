<?php

namespace App\Http\Requests\Campaign\Statistics;

use App\Http\Requests\FilterableRequest;

class GetCampaignStatisticsRequest extends FilterableRequest
{
    /**
     * Fields that are allowed to be filtered for Channel model.
     *
     * @var array
     */
    protected array $filterableFields = [
        'id',
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
