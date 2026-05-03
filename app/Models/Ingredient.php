<?php

namespace App\Models;

use App\Enums\IngredientCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingredient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category',
    ];

    protected $casts = [
        'category' => IngredientCategory::class,
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Product::class);
    }
}
