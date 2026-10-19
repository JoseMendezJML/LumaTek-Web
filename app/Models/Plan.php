<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'max_greenhouses',
        'max_zones_per_greenhouse',
        'max_sensors_per_zone',
        'max_users',
        'max_additional_admins',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'max_greenhouses' => 'integer',
            'max_zones_per_greenhouse' => 'integer',
            'max_sensors_per_zone' => 'integer',
            'max_users' => 'integer',
            'max_additional_admins' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class
        );
    }
}