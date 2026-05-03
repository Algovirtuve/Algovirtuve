<?php

namespace App\Models;

use App\Enums\Measurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Macroelement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title',
        'measurement',
    ];

    protected $casts = [
        'measurement' => Measurement::class,
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_macroelement')
            ->withPivot(['measurement', 'quantity']);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_macroelement')
            ->withPivot(['measurement', 'daily_rate']);
    }

    public function dietPlans(): BelongsToMany
    {
        return $this->belongsToMany(DietPlan::class, 'diet_plan_macroelement');
    }

    public function ingredientMacroelements(): HasMany
    {
        return $this->hasMany(IngredientMacroelement::class);
    }

    public function userMacroelements(): HasMany
    {
        return $this->hasMany(UserMacroelement::class);
    }
}
