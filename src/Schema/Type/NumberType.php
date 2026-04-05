<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class NumberType implements SchemaType
{
    public function __construct(
        public int|float|null $min = null,
        public int|float|null $max = null,
    ) {}

    public function describe(): string
    {
        $constraints = [];
        if ($this->min !== null) {
            $constraints[] = 'min: ' . $this->min;
        }
        if ($this->max !== null) {
            $constraints[] = 'max: ' . $this->max;
        }
        if ($constraints === []) {
            return 'number';
        }
        return 'number(' . \implode(', ', $constraints) . ')';
    }
}
