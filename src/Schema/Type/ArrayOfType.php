<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class ArrayOfType implements SchemaType
{
    public function __construct(
        public SchemaType $items,
    ) {}

    public function describe(): string
    {
        return $this->items->describe() . '[]';
    }
}
