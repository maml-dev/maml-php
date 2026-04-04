<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class IntegerType implements SchemaType
{
    public function describe(): string
    {
        return 'integer';
    }
}
