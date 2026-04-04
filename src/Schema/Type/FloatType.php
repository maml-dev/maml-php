<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class FloatType implements SchemaType
{
    public function describe(): string
    {
        return 'float';
    }
}
