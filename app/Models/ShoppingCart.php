<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingCart extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'price',
        'store_id',
        'shopping_plan_id',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shoppingPlan(): BelongsTo
    {
        return $this->belongsTo(ShoppingPlan::class);
    }
}
