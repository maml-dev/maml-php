<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class IntegerNode
{
    public string $type;

    public function __construct(
        public int $value,
        public string $raw,
        public Span $span,
    ) {
        $this->type = 'Integer';
    }
}
