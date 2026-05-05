<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'preference_status' => $this->resource['preference_status'],
            'status_label' => $this->resource['status_label'],
            'generation_date' => $this->resource['generation_date'],
            'score' => $this->resource['score'],
            'missing_ingredients_count' => $this->resource['missing_ingredients_count'],
            'matched_tools_count' => $this->resource['matched_tools_count'],
            'available_ingredients_count' => $this->resource['available_ingredients_count'],
            'recipe' => $this->resource['recipe'],
        ];
    }
}
