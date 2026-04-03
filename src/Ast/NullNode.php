<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class NullNode
{
    public string $type;
    public null $value;

    public function __construct(
        public Span $span,
    ) {
        $this->type = 'Null';
        $this->value = null;
    }
}
