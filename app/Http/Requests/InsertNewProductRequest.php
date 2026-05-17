<?php

namespace App\Http\Requests;

use App\Enums\Measurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsertNewProductRequest extends FormRequest
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
            'product_title' => ['required', 'string', 'max:255'],

            // If provided, we treat the request as the "selected shop" step.
            'store_title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],

            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'measurement' => ['nullable', 'string', Rule::in(Measurement::all())],
        ];
    }

    public function productTitle(): string
    {
        return trim((string) $this->input('product_title'));
    }

    public function hasSelectedShop(): bool
    {
        return $this->filled('store_title');
    }

    /**
     * @return array{title: string, address: string, city: string}
     */
    public function selectedShop(): array
    {
        return [
            'title' => (string) $this->input('store_title', ''),
            'address' => (string) $this->input('address', ''),
            'city' => (string) $this->input('city', ''),
        ];
    }

    /**
     * @return array{price: float, quantity: int, measurement: string}
     */
    public function selectedProduct(): array
    {
        return [
            'price' => (float) $this->input('price', 0),
            'quantity' => (int) $this->input('quantity', 1),
            'measurement' => (string) $this->input('measurement', Measurement::UNIT->value),
        ];
    }
}
