<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class BooleanNode
{
    public string $type;

    public function __construct(
        public bool $value,
        public Span $span,
    ) {
        $this->type = 'Boolean';
    }
}
