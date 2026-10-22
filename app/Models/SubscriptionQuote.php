<?php

namespace App\Models;

use App\Database\ImmutableQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionQuote extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function newBaseQueryBuilder()
    {
        return new ImmutableQueryBuilder($this->getConnection(), allowCapture: true);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'terms' => 'array', 'issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function confirmation(): HasOne
    {
        return $this->hasOne(SubscriptionManualConfirmation::class, 'quote_id');
    }
}
