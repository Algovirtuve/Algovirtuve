<?php

namespace App\Http\Requests;

use App\Enums\PreferenceStatus;
use App\Models\Preference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DislikeSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $preference = $this->route('preference');

        return $preference instanceof Preference
            && $preference->user_id === $this->user()?->getKey()
            && $preference->preference_status === PreferenceStatus::Awaiting;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
