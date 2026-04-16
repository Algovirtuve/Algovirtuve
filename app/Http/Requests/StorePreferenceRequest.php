<?php

namespace App\Http\Requests;

use App\Enums\PreferenceStatus;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists(Recipe::class, 'id')->where(
                    fn ($query) => $query->where('status', RecipeStatus::Accepted->value),
                ),
                Rule::unique('preferences', 'recipe_id')->where(fn ($query) => $query->where('user_id', $this->user()?->getAuthIdentifier())),
            ],
            'preference_status' => ['required', Rule::enum(PreferenceStatus::class)],
        ];
    }
}
