<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class FloatType implements SchemaType
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
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
            return 'float';
        }
        return 'float(' . \implode(', ', $constraints) . ')';
    }
}
