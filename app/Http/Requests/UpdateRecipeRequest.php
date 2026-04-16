<?php

namespace App\Http\Requests;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recipe = $this->route('recipe');

        return $recipe instanceof Recipe
            && $recipe->user_id === $this->user()?->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'preparation_time' => ['required', 'string', 'max:255'],
            'servings' => ['required', 'integer', 'min:1', 'max:255'],
            'difficulty' => ['required', Rule::enum(RecipeDifficulty::class)],
            'calorie_intake' => ['required', 'integer', 'min:0'],
            'diet_type' => ['required', Rule::enum(DietType::class)],
            'meal' => ['required', Rule::enum(Meal::class)],
        ];
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
