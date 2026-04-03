<?php

declare(strict_types=1);

namespace Maml\Ast;

class Document
{
    public string $type = 'Document';

    /** @var CommentNode[] */
    public array $leadingComments = [];

    /** @var CommentNode[] */
    public array $danglingComments = [];

    public function __construct(
        public readonly StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $value,
        public readonly Span $span,
    ) {}
}
