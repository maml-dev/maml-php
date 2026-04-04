<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class StringType implements SchemaType
{
    public function describe(): string
    {
        return 'string';
    }
}
