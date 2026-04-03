<?php

declare(strict_types=1);

namespace Maml\Ast;

class ObjectNode
{
    public string $type = 'Object';

    /** @var CommentNode[] */
    public array $danglingComments = [];

    /** @param Property[] $properties */
    public function __construct(
        public readonly array $properties,
        public readonly Span $span,
    ) {}
}
