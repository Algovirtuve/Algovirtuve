<?php

namespace App\Models;

use App\Enums\IngredientCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient')
            ->withPivot(['quantity', 'measurement', 'importance']);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_ingredient')
            ->withPivot(['quantity', 'expiry_date']);
    }

    public function macroelements(): BelongsToMany
    {
        return $this->belongsToMany(Macroelement::class, 'ingredient_macroelement')
            ->withPivot(['measurement', 'quantity']);
    }

    public function ingredientMacroelements(): HasMany
    {
        return $this->hasMany(IngredientMacroelement::class);
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function userIngredients(): HasMany
    {
        return $this->hasMany(UserIngredient::class);
    }
}
