<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProductsPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipe_ids' => ['required', 'array', 'min:1'],
            'recipe_ids.*' => ['integer', 'distinct', 'exists:recipes,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function recipeIds(): array
    {
        return collect($this->input('recipe_ids', []))
            ->filter(fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
