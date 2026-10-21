<?php

namespace App\Models;

use App\Database\ImmutableQueryBuilder;

// Read-only Plan projection preserves the return type expected by existing
// controllers while preventing historical terms from overwriting the catalog.
class ContractedPlan extends Plan
{
    protected $table = 'plans';

    protected function newBaseQueryBuilder()
    {
        return new ImmutableQueryBuilder($this->getConnection());
    }

    public function save(array $options = [])
    {
        throw new \LogicException('Contracted plan conditions are read-only.');
    }

    public function delete()
    {
        throw new \LogicException('Contracted plan conditions are read-only.');
    }
}
