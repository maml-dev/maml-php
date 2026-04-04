<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class OptionalType implements SchemaType
{
    public function __construct(
        public SchemaType $inner,
    ) {}

    public function describe(): string
    {
        return $this->inner->describe() . '?';
    }
}
