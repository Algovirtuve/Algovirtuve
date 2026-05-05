<?php

namespace App\Models;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'title',
    'image_path',
    'instructions',
    'preparation_time',
    'servings',
    'difficulty',
    'calorie_intake',
    'status',
    'diet_type',
    'meal',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diet_type' => DietType::class,
            'meal' => Meal::class,
            'difficulty' => RecipeDifficulty::class,
            'status' => RecipeStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Preference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(Preference::class);
    }

    /**
     * @return BelongsToMany<User, $this, Preference>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'preferences')
            ->withPivot(['id', 'preference_status', 'generation_date']);
    }

    /**
     * @return BelongsToMany<DietPlan, $this>
     */
    public function dietPlans(): BelongsToMany
    {
        return $this->belongsToMany(DietPlan::class, 'diet_plan_recipe');
    }

    /**
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'recipe_tool');
    }

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')
            ->withPivot(['quantity', 'measurement', 'importance']);
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
