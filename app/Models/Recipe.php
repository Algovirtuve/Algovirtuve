<?php

namespace App\Models;

use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'instructions',
    'preparation_time_minutes',
    'servings',
    'difficulty',
    'calorie_count',
    'status',
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
            'difficulty' => RecipeDifficulty::class,
            'status' => RecipeStatus::class,
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
     * @return BelongsToMany<User, $this, Preference>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'preferences')
            ->withPivot(['id', 'status'])
            ->withTimestamps();
    }
}
