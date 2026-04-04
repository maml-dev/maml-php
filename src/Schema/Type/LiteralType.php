<?php

declare(strict_types=1);

namespace Maml\Schema\Type;

use Maml\Schema\SchemaType;

readonly class LiteralType implements SchemaType
{
    public function __construct(
        public string|int|float|bool|null $value,
    ) {}

    public function describe(): string
    {
        if ($this->value === null) {
            return 'null';
        }
        if (\is_bool($this->value)) {
            return $this->value ? 'true' : 'false';
        }
        if (\is_string($this->value)) {
            return '"' . $this->value . '"';
        }
        return (string) $this->value;
    }
}
