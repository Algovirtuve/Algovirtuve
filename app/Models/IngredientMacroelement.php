<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientMacroelement extends Model
{
    protected $table = 'ingredient_macroelement';

    protected $fillable = [
        'measurement',
        'quantity',
        'ingredient_id',
        'macroelement_id',
    ];

    public $timestamps = false;

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function macroelement(): BelongsTo
    {
        return $this->belongsTo(Macroelement::class);
    }
}
