<?php

namespace App\Models;

use App\Database\ImmutableQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Records external verification by an administrator, never a processed gateway payment.
class SubscriptionManualConfirmation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function newBaseQueryBuilder()
    {
        return new ImmutableQueryBuilder($this->getConnection(), allowCapture: true);
    }

    protected function casts(): array
    {
        return ['confirmed_at' => 'immutable_datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SubscriptionContract::class, 'contract_id');
    }
}
