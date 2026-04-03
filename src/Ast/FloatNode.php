<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class FloatNode
{
    public string $type;

    public function __construct(
        public float $value,
        public string $raw,
        public Span $span,
    ) {
        $this->type = 'Float';
    }
}
