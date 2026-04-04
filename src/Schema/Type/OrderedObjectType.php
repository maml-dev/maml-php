<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class OrderedObjectType implements SchemaType
{
    /** @param array<string, SchemaType> $properties */
    public function __construct(
        public array $properties,
    ) {}

    public function describe(): string
    {
        return 'ordered object{' . \implode(', ', \array_keys($this->properties)) . '}';
    }
}
