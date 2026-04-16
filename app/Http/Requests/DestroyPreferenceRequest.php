<?php

namespace App\Http\Requests;

use App\Models\Preference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DestroyPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $preference = $this->route('preference');

        return $preference instanceof Preference
            && $preference->user_id === $this->user()?->getKey();
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
