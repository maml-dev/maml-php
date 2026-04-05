<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class MapType implements SchemaType
{
    public function __construct(
        public SchemaType $values,
    ) {}

    public function describe(): string
    {
        return 'map<' . $this->values->describe() . '>';
    }
}
