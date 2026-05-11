<?php

namespace App\Http\Requests;

use App\Enums\RecipeStatus;
use App\Models\Request as AdminRequest;
use Illuminate\Foundation\Http\FormRequest;

class ApproveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->administrator()->exists()) {
            return false;
        }

        $requestModel = $this->route('request');

        if (! $requestModel instanceof AdminRequest) {
            return false;
        }

        return $requestModel->recipe()->where('status', RecipeStatus::Draft->value)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
