<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class NumberType implements SchemaType
{
    public function describe(): string
    {
        return 'number';
    }
}
