<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessAccount extends Model
{
    protected $fillable = [
        'business_id',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
