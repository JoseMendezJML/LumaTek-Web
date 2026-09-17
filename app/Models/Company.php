<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'status',
    ];

    /**
     * Usuarios que pertenecen a la empresa.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Invernaderos que pertenecen a la empresa.
     */
    public function greenhouses(): HasMany
    {
        return $this->hasMany(Greenhouse::class);
    }
}