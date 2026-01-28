<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'category',
        'owner_type',
        'owner_id',
        'currency',
        'is_system_account',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system_account' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get account by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Get system account by category
     */
    public static function getSystemAccount(string $category, string $currency = 'ZAR'): ?self
    {
        return static::where('category', $category)
            ->where('is_system_account', true)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get business account by category
     */
    public static function getBusinessAccount(int $businessId, string $category, string $currency = 'ZAR'): ?self
    {
        return static::where('owner_type', Business::class)
            ->where('owner_id', $businessId)
            ->where('category', $category)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();
    }
}
