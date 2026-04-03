<?php

declare(strict_types=1);

namespace Maml\Ast;

class Property
{
    /** @var CommentNode[] */
    public array $leadingComments = [];
    public ?CommentNode $trailingComment = null;
    public bool $emptyLineBefore = false;

    public function __construct(
        public readonly IdentifierKey|StringNode $key,
        public readonly StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $value,
        public readonly Span $span,
    ) {}
}
