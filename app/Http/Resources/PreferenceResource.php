<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preference_status' => $this->preference_status->value,
            'status_label' => $this->preference_status->label(),
            'generation_date' => $this->generation_date?->toDateString(),
            'recipe' => RecipeResource::make($this->recipe),
        ];
    }
}
