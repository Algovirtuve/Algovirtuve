<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMacroelement extends Model
{
    protected $table = 'user_macroelement';

    protected $fillable = [
        'measurement',
        'daily_rate',
        'user_id',
        'macroelement_id',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function macroelement(): BelongsTo
    {
        return $this->belongsTo(Macroelement::class);
    }
}
