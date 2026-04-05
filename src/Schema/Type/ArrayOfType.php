<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class ArrayOfType implements SchemaType
{
    public function __construct(
        public SchemaType $items,
        public ?int $minItems = null,
        public ?int $maxItems = null,
    ) {}

    public function describe(): string
    {
        $base = $this->items->describe() . '[]';
        $constraints = [];
        if ($this->minItems !== null) {
            $constraints[] = 'minItems: ' . $this->minItems;
        }
        if ($this->maxItems !== null) {
            $constraints[] = 'maxItems: ' . $this->maxItems;
        }
        if ($constraints === []) {
            return $base;
        }
        return $base . '(' . \implode(', ', $constraints) . ')';
    }
}
