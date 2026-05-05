<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
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
            'title' => $this->title,
            'image_url' => $this->image_path === null ? null : asset('storage/'.$this->image_path),
            'instructions' => $this->instructions,
            'preparation_time' => $this->preparation_time,
            'servings' => $this->servings,
            'difficulty' => $this->difficulty->value,
            'difficulty_label' => $this->difficulty->label(),
            'calorie_intake' => $this->calorie_intake,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'diet_type' => $this->diet_type->value,
            'diet_type_label' => $this->diet_type->label(),
            'meal' => $this->meal->value,
            'meal_label' => $this->meal->label(),
            'ingredients' => $this->relationLoaded('ingredients')
                ? $this->ingredients->map(static fn ($ingredient): array => [
                    'id' => $ingredient->id,
                    'title' => $ingredient->product?->title ?? 'Ingredient',
                    'importance' => (bool) $ingredient->pivot?->importance,
                ])->values()->all()
                : [],
            'tools' => $this->relationLoaded('tools')
                ? $this->tools->map(static fn ($tool): array => [
                    'id' => $tool->id,
                    'title' => $tool->product?->title ?? $tool->type->value,
                ])->values()->all()
                : [],
        ];
    }
}
