<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class UnionType implements SchemaType
{
    /** @param SchemaType[] $branches */
    public function __construct(
        public array $branches,
    ) {}

    public function describe(): string
    {
        $parts = \array_map(fn(SchemaType $s) => $s->describe(), $this->branches);
        return \implode(' | ', $parts);
    }
}
