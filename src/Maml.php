<?php

declare(strict_types=1);

namespace Maml;

use Maml\Ast\ArrayNode;
use Maml\Ast\BooleanNode;
use Maml\Ast\Document;
use Maml\Ast\Element;
use Maml\Ast\FloatNode;
use Maml\Ast\IntegerNode;
use Maml\Ast\NullNode;
use Maml\Ast\ObjectNode;
use Maml\Ast\RawStringNode;
use Maml\Ast\StringNode;

final class Maml
{
    public static function parse(string $source): mixed
    {
        return Parser::parse($source);
    }

    public static function stringify(mixed $value): string
    {
        return Stringifier::stringify($value);
    }

    public static function parseAst(string $source): Document
    {
        return AstParser::parse($source);
    }

    public static function printAst(
        Document|StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
    ): string {
        return AstPrinter::print($node);
    }

    public static function toValue(
        Document|StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
    ): mixed {
        if ($node instanceof Document) {
            return self::toValue($node->value);
        }
        if ($node instanceof StringNode || $node instanceof RawStringNode) {
            return $node->value;
        }
        if ($node instanceof IntegerNode || $node instanceof FloatNode) {
            return $node->value;
        }
        if ($node instanceof BooleanNode) {
            return $node->value;
        }
        if ($node instanceof NullNode) {
            return null;
        }
        if ($node instanceof ArrayNode) {
            return \array_map(
                fn(Element $el) => self::toValue($el->value),
                $node->elements,
            );
        }
        // $node is ObjectNode at this point (all other types handled above)
        $result = [];
        foreach ($node->properties as $prop) {
            $result[$prop->key->value] = self::toValue($prop->value);
        }
        return $result;
    }
}
