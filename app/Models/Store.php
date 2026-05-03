<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title',
        'address',
        'city',
    ];

    public function shoppingCarts()
    {
        return $this->hasMany(ShoppingCart::class);
    }

    public function storeProducts()
    {
        return $this->hasMany(StoreProduct::class);
    }
}
