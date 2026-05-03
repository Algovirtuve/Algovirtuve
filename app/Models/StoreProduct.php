<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProduct extends Model
{
    protected $table = 'store_product';

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_id',
        'price',
        'quantity',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shoppingPlan(): BelongsTo
    {
        return $this->belongsTo(ShoppingPlan::class);
    }
}
