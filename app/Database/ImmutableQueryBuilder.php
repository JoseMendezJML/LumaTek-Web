<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use LogicException;

// Also guards toBase()/getQuery(), not just Eloquent mutation entry points.
class ImmutableQueryBuilder extends Builder
{
    public function __construct($connection, ?Grammar $grammar = null, ?Processor $processor = null, private readonly bool $allowCapture = false)
    {
        parent::__construct($connection, $grammar, $processor);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        if (! $this->allowCapture) {
            throw new LogicException('Historical models are read-only; use subscription capture.');
        }

        return parent::insertGetId($values, $sequence);
    }

    public function insert(array $values)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function insertOrIgnore(array $values)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function insertUsing(array $columns, $query)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function update(array $values)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function updateFrom(array $values)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function delete($id = null)
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }

    public function truncate()
    {
        throw new LogicException('Historical models are read-only; use subscription capture.');
    }
}
