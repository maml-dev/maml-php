<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class CommentNode
{
    public string $type;

    public function __construct(
        public string $value,
        public Span $span,
    ) {
        $this->type = 'Comment';
    }
}
