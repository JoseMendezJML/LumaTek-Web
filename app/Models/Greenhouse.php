<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Greenhouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'crop_type',
        'area',
        'location',
        'planting_date',
        'nominal_flow',
        'status',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'nominal_flow' => 'decimal:2',
        'planting_date' => 'date',
    ];

    /**
     * Empresa propietaria del invernadero.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Umbrales configurados para este invernadero.
     */
    public function thresholds(): HasMany
    {
        return $this->hasMany(GreenhouseThreshold::class);
    }

    /**
     * Zonas configuradas dentro del invernadero.
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}