<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class ObjectType implements SchemaType
{
    /** @param array<string, SchemaType> $properties */
    public function __construct(
        public array $properties,
    ) {}

    public function describe(): string
    {
        return 'object{' . \implode(', ', \array_keys($this->properties)) . '}';
    }
}
