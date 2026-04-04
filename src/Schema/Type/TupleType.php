<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class TupleType implements SchemaType
{
    /** @param SchemaType[] $elements */
    public function __construct(
        public array $elements,
    ) {}

    public function describe(): string
    {
        $parts = \array_map(fn(SchemaType $s) => $s->describe(), $this->elements);
        return '[' . \implode(', ', $parts) . ']';
    }
}
