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
use Maml\Ast\Position;
use Maml\Ast\RawStringNode;
use Maml\Ast\StringNode;
use Maml\Schema\SchemaType;
use Maml\Schema\ValidationError;
use Maml\Schema\Validator;

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

    /**
     * @return ValidationError[]
     */
    public static function validate(Document $doc, SchemaType $schema): array
    {
        return Validator::validate($doc, $schema);
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

    public static function errorSnippet(string $source, Position $pos, string $message): string
    {
        $offset = $pos->offset;
        $pre = \substr($source, \max(0, $offset - 40), \min($offset, 40));
        $lines = \explode("\n", $pre);
        $lastLine = \end($lines) ?: '';
        $postParts = \explode("\n", \substr($source, $offset, 40), 2);
        $postfix = $postParts[0];

        $snippet = "    {$lastLine}{$postfix}\n";
        $pointer = '    ' . \str_repeat('.', \strlen($lastLine)) . "^\n";
        return "{$message} on line {$pos->line}.\n\n{$snippet}{$pointer}";
    }
}
