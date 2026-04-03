<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class RawStringNode
{
    public string $type;

    public function __construct(
        public string $value,
        public string $raw,
        public Span $span,
    ) {
        $this->type = 'RawString';
    }
}
