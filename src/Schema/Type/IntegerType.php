<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class IntegerType implements SchemaType
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
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
            return 'integer';
        }
        return 'integer(' . \implode(', ', $constraints) . ')';
    }
}
