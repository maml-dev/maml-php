<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class NullType implements SchemaType
{
    public function describe(): string
    {
        return 'null';
    }
}
