<?php

declare(strict_types=1);

namespace Maml\Ast;

class ArrayNode
{
    public string $type = 'Array';

    /** @var CommentNode[] */
    public array $danglingComments = [];

    /** @param Element[] $elements */
    public function __construct(
        public readonly array $elements,
        public readonly Span $span,
    ) {}
}
