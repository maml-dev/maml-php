<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class StringType implements SchemaType
{
    public function __construct(
        public ?string $pattern = null,
    ) {}

    public function describe(): string
    {
        if ($this->pattern !== null) {
            return 'string(pattern: ' . $this->pattern . ')';
        }
        return 'string';
    }
}
