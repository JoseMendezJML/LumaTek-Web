<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanChange extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'recorded_at' => 'datetime'];
    }
}
