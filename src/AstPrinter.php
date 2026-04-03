<?php

declare(strict_types=1);

namespace Maml;

use Maml\Ast\ArrayNode;
use Maml\Ast\BooleanNode;
use Maml\Ast\CommentNode;
use Maml\Ast\Document;
use Maml\Ast\FloatNode;
use Maml\Ast\IntegerNode;
use Maml\Ast\NullNode;
use Maml\Ast\ObjectNode;
use Maml\Ast\RawStringNode;
use Maml\Ast\StringNode;

final class AstPrinter
{
    public static function print(
        Document|StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
    ): string {
        if ($node instanceof Document) {
            $out = '';
            foreach ($node->leadingComments as $c) {
                $out .= '#' . $c->value . "\n";
            }
            $out .= self::doPrint($node->value, 0);
            foreach ($node->danglingComments as $c) {
                $out .= "\n" . '#' . $c->value;
            }
            return $out;
        }
        return self::doPrint($node, 0);
    }

    private static function printComments(array $comments, string $indent): string
    {
        $out = '';
        foreach ($comments as $c) {
            $out .= $indent . '#' . $c->value . "\n";
        }
        return $out;
    }

    private static function doPrint(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        int $level,
    ): string {
        if ($node instanceof StringNode) {
            return self::quoteString($node->value);
        }
        if ($node instanceof RawStringNode) {
            return $node->raw;
        }
        if ($node instanceof IntegerNode || $node instanceof FloatNode) {
            return $node->raw;
        }
        if ($node instanceof BooleanNode) {
            return $node->value ? 'true' : 'false';
        }
        if ($node instanceof NullNode) {
            return 'null';
        }
        if ($node instanceof ArrayNode) {
            $len = \count($node->elements);
            $hasComments = \count($node->danglingComments) > 0;
            if ($len === 0 && !$hasComments) {
                return '[]';
            }

            $childIndent = self::getIndent($level + 1);
            $parentIndent = self::getIndent($level);
            $out = "[\n";

            for ($i = 0; $i < $len; $i++) {
                $el = $node->elements[$i];

                if ($i > 0) {
                    $out .= "\n";
                    if ($el->emptyLineBefore) $out .= "\n";
                }
                $out .= self::printComments($el->leadingComments, $childIndent);
                $out .= $childIndent . self::doPrint($el->value, $level + 1);

                if ($el->trailingComment !== null) {
                    $out .= ' #' . $el->trailingComment->value;
                }
            }

            if (\count($node->danglingComments) > 0) {
                if ($len > 0) $out .= "\n";
                $out .= self::printComments($node->danglingComments, $childIndent);
                $out = \rtrim($out, "\n");
            }

            return $out . "\n" . $parentIndent . ']';
        }
        if ($node instanceof ObjectNode) {
            $len = \count($node->properties);
            $hasComments = \count($node->danglingComments) > 0;
            if ($len === 0 && !$hasComments) {
                return '{}';
            }

            $childIndent = self::getIndent($level + 1);
            $parentIndent = self::getIndent($level);
            $out = "{\n";

            for ($i = 0; $i < $len; $i++) {
                $prop = $node->properties[$i];

                if ($i > 0) {
                    $out .= "\n";
                    if ($prop->emptyLineBefore) $out .= "\n";
                }
                $out .= self::printComments($prop->leadingComments, $childIndent);

                $keyStr = $prop->key->type === 'Identifier'
                    ? $prop->key->value
                    : self::quoteString($prop->key->value);
                $out .= $childIndent . $keyStr . ': ' . self::doPrint($prop->value, $level + 1);

                if ($prop->trailingComment !== null) {
                    $out .= ' #' . $prop->trailingComment->value;
                }
            }

            if (\count($node->danglingComments) > 0) {
                if ($len > 0) $out .= "\n";
                $out .= self::printComments($node->danglingComments, $childIndent);
                $out = \rtrim($out, "\n");
            }

            return $out . "\n" . $parentIndent . '}';
        }
        throw new \InvalidArgumentException('Unknown node type');
    }

    private static function quoteString(string $s): string
    {
        $out = '"';
        $len = \mb_strlen($s, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $c = \mb_substr($s, $i, 1, 'UTF-8');
            $code = \mb_ord($c, 'UTF-8');
            if ($c === '"') {
                $out .= '\\"';
            } elseif ($c === '\\') {
                $out .= '\\\\';
            } elseif ($c === "\n") {
                $out .= '\\n';
            } elseif ($c === "\r") {
                $out .= '\\r';
            } elseif ($c === "\t") {
                $out .= '\\t';
            } elseif ($code < 0x20 || $code === 0x7F) {
                $out .= '\\u{' . \strtoupper(\dechex($code)) . '}';
            } else {
                $out .= $c;
            }
        }
        return $out . '"';
    }

    private static function getIndent(int $level): string
    {
        return \str_repeat('  ', $level);
    }
}
