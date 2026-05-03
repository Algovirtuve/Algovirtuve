<?php

namespace App\Models;

use App\Enums\PreferenceStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'surname', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Preference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(Preference::class);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function createdRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return BelongsToMany<Recipe, $this, Preference>
     */
    public function preferredRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'preferences')
            ->withPivot(['id', 'preference_status', 'generation_date']);
    }

    /**
     * @return BelongsToMany<Recipe, $this, Preference>
     */
    public function favoriteRecipes(): BelongsToMany
    {
        return $this->preferredRecipes()
            ->wherePivot('preference_status', PreferenceStatus::Liked->value);
    }

    public function shoppingPlans(): HasMany
    {
        return $this->hasMany(ShoppingPlan::class);
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'user_tool');
    }

    public function macroelements(): BelongsToMany
    {
        return $this->belongsToMany(Macroelement::class, 'user_macroelement')
            ->withPivot(['measurement', 'daily_rate']);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'user_ingredient')
            ->withPivot(['quantity', 'expiry_date']);
    }

    public function userMacroelements(): HasMany
    {
        return $this->hasMany(UserMacroelement::class);
    }

    public function userIngredients(): HasMany
    {
        return $this->hasMany(UserIngredient::class);
    }
}
