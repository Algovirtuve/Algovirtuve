<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DietPlan extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'diet_type',
    ];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'diet_plan_recipe');
    }

    public function macroelements(): BelongsToMany
    {
        return $this->belongsToMany(Macroelement::class, 'diet_plan_macroelement');
    }
}
