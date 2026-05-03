<?php

namespace App\Models;

use App\Enums\ToolType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tool extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
    ];

    protected $casts = [
        'type' => ToolType::class,
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Product::class);
    }
}
