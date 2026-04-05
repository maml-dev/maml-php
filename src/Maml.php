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
use Maml\Ast\Span;
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

    public static function errorSnippet(
        string $source,
        Position|Span $location,
        string $message,
        int $context = 0,
    ): string {
        $start = $location instanceof Span ? $location->start : $location;
        $lineNum = $start->line;

        $sourceLines = \explode("\n", $source);
        $lineIndex = $lineNum - 1;
        $errorLine = $sourceLines[$lineIndex] ?? '';

        // Convert byte column to character column (0-based)
        $byteCol = $start->column - 1;
        $charCol = \mb_strlen(\substr($errorLine, 0, $byteCol), 'UTF-8');

        // Calculate underline width in characters
        if ($location instanceof Span) {
            if ($location->end->line === $lineNum) {
                $endByteCol = $location->end->column - 1;
                $width = \mb_strlen(\substr($errorLine, $byteCol, $endByteCol - $byteCol), 'UTF-8');
            } else {
                $width = \mb_strlen($errorLine, 'UTF-8') - $charCol;
            }
        } else {
            $width = 1;
        }
        $width = \max(1, $width);

        // Collect lines to display (context + error line)
        $firstIndex = \max(0, $lineIndex - $context);
        $lines = [];
        for ($i = $firstIndex; $i <= $lineIndex; $i++) {
            $lines[] = $sourceLines[$i] ?? '';
        }

        // Truncation (work in character space)
        $maxWidth = 70;
        $lineCharLen = \mb_strlen($errorLine, 'UTF-8');
        $adjustedCol = $charCol;

        if ($lineCharLen > $maxWidth) {
            // Calculate window centered on error
            $windowStart = $charCol - \intdiv($maxWidth, 3);
            $windowStart = \max(0, $windowStart);
            if ($windowStart + $maxWidth < $charCol + $width) {
                $windowStart = \max(0, $charCol + $width - $maxWidth);
            }
            if ($windowStart + $maxWidth > $lineCharLen) {
                $windowStart = \max(0, $lineCharLen - $maxWidth);
            }

            $leftTrim = $windowStart > 0;
            $rightTrim = ($windowStart + $maxWidth) < $lineCharLen;

            for ($i = 0; $i < \count($lines); $i++) {
                $visible = \mb_substr($lines[$i], $windowStart, $maxWidth, 'UTF-8');
                if ($leftTrim && \mb_strlen($visible, 'UTF-8') > 0) {
                    $visible = '…' . \mb_substr($visible, 1, null, 'UTF-8');
                }
                if ($rightTrim && \mb_strlen($visible, 'UTF-8') > 0) {
                    $visible = \mb_substr($visible, 0, -1, 'UTF-8') . '…';
                }
                $lines[$i] = $visible;
            }

            $adjustedCol = $charCol - $windowStart;
        }

        // Build output
        $indent = '    ';
        $out = "{$message} on line {$lineNum}.\n\n";
        foreach ($lines as $line) {
            $out .= $indent . $line . "\n";
        }
        $out .= $indent . \str_repeat(' ', $adjustedCol) . \str_repeat('^', $width) . "\n";

        return $out;
    }
}
