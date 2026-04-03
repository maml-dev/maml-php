<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class IdentifierKey
{
    public string $type;

    public function __construct(
        public string $value,
        public Span $span,
    ) {
        $this->type = 'Identifier';
    }
}
