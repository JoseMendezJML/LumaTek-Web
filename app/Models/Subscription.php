<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    use HasFactory;

    private array $contractContext = [];

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            Plan::class
        );
    }

    public function contract(): HasOne
    {
        return $this->hasOne(SubscriptionContract::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where(fn ($dates) => $dates->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && now()->greaterThanOrEqualTo($this->ends_at);
    }

    public function withContractContext(string $period, string $origin, string $requestKey): static
    {
        if ($this->exists || ! in_array($period, ['monthly', 'yearly'], true) || ! in_array($origin, ['purchase', 'renewal'], true)) {
            throw new \InvalidArgumentException('Invalid new subscription contract context.');
        }
        $this->contractContext = ['billing_period' => $period, 'origin' => $origin, 'request_key' => $requestKey];

        return $this;
    }

    public function save(array $options = [])
    {
        // Keep identity/dates and the initial snapshot atomic, including quiet saves.
        return DB::transaction(function () use ($options) {
            $creating = ! $this->exists;
            if (! $creating && $this->isDirty(['company_id', 'plan_id', 'starts_at', 'ends_at'])) {
                throw new \LogicException('Create a new subscription period instead of rewriting contracted identity or dates.');
            }
            $saved = parent::save($options);
            if ($creating && $saved) {
                SubscriptionContract::capture($this, $this->contractContext);
            }

            return $saved;
        });
    }
}
