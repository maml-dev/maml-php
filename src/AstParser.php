<?php

declare(strict_types=1);

namespace Maml;

use Maml\Ast\ArrayNode;
use Maml\Ast\BooleanNode;
use Maml\Ast\CommentNode;
use Maml\Ast\Document;
use Maml\Ast\Element;
use Maml\Ast\FloatNode;
use Maml\Ast\IdentifierKey;
use Maml\Ast\IntegerNode;
use Maml\Ast\NullNode;
use Maml\Ast\ObjectNode;
use Maml\Ast\Position;
use Maml\Ast\Property;
use Maml\Ast\RawStringNode;
use Maml\Ast\Span;
use Maml\Ast\StringNode;

final class AstParser
{
    private const ESCAPE_MAP = [
        '"' => '"',
        '\\' => '\\',
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
    ];

    private string $source;
    private int $pos = 0;
    private int $lineNumber = 1;
    private int $columnNumber = 0;
    private string $ch = '';
    private bool $done = false;

    /** @var CommentNode[] */
    private array $comments = [];

    private function __construct(string $source)
    {
        $this->source = $source;
        $this->next();
    }

    public static function parse(string $source): Document
    {
        $parser = new self($source);
        $docStart = new Position(0, 1, 1);

        $value = $parser->parseValue();
        $parser->skipWhitespace();

        if (!$parser->done) {
            throw new ParseException($parser->errorSnippet());
        }

        $parser->expectValue($value);
        $docEnd = $parser->here();

        $doc = new Document($value, new Span($docStart, $docEnd));
        self::attachComments($doc, $parser->comments, $source);
        self::attachBlankLines($doc->value, $source);
        return $doc;
    }

    private function next(): void
    {
        if ($this->pos < \strlen($this->source)) {
            $this->ch = $this->source[$this->pos];
            $this->pos++;
            if ($this->ch === "\n") {
                $this->lineNumber++;
                $this->columnNumber = 0;
            } else {
                $this->columnNumber++;
            }
        } else {
            $this->ch = '';
            $this->done = true;
        }
    }

    private function here(): Position
    {
        if ($this->done) {
            return new Position(
                \strlen($this->source),
                $this->lineNumber,
                $this->columnNumber + 1,
            );
        }
        return new Position(
            $this->pos - 1,
            $this->lineNumber,
            $this->columnNumber,
        );
    }

    private function lookahead(int $n): string
    {
        return \substr($this->source, $this->pos, $n);
    }

    private function parseValue(): StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode|null
    {
        $this->skipWhitespace();
        return $this->parseRawString()
            ?? $this->parseString()
            ?? $this->parseNumber()
            ?? $this->parseObject()
            ?? $this->parseArray()
            ?? $this->parseKeyword('true')
            ?? $this->parseKeyword('false')
            ?? $this->parseKeyword('null');
    }

    private function parseString(): ?StringNode
    {
        if ($this->ch !== '"') {
            return null;
        }
        $start = $this->here();
        $str = '';
        $escaped = false;
        while (true) {
            $this->next();
            if ($this->done) {
                throw new ParseException($this->errorSnippet());
            }
            if ($escaped) {
                if ($this->ch === 'u') {
                    $this->next();
                    if ($this->ch !== '{') {
                        throw new ParseException(
                            $this->errorSnippet(
                                'Invalid escape sequence ' . $this->formatChar($this->ch) . ' (expected "{")',
                            ),
                        );
                    }
                    $hex = '';
                    while (true) {
                        $this->next();
                        if ($this->ch === '}') {
                            break;
                        }
                        if (!$this->isHexDigit($this->ch)) {
                            throw new ParseException(
                                $this->errorSnippet(
                                    'Invalid escape sequence ' . $this->formatChar($this->ch),
                                ),
                            );
                        }
                        $hex .= $this->ch;
                        if (\strlen($hex) > 6) {
                            throw new ParseException(
                                $this->errorSnippet('Invalid escape sequence (too many hex digits)'),
                            );
                        }
                    }
                    if ($hex === '') {
                        throw new ParseException(
                            $this->errorSnippet('Invalid escape sequence'),
                        );
                    }
                    $codePoint = (int) \hexdec($hex);
                    if ($codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
                        throw new ParseException(
                            $this->errorSnippet('Invalid escape sequence (out of range)'),
                        );
                    }
                    $str .= self::codePointToUtf8($codePoint);
                } else {
                    $escapedChar = self::ESCAPE_MAP[$this->ch] ?? null;
                    if ($escapedChar === null) {
                        throw new ParseException(
                            $this->errorSnippet(
                                'Invalid escape sequence ' . $this->formatChar($this->ch),
                            ),
                        );
                    }
                    $str .= $escapedChar;
                }
                $escaped = false;
            } elseif ($this->ch === '\\') {
                $escaped = true;
            } elseif ($this->ch === '"') {
                break;
            } elseif ($this->ch === "\n") {
                throw new ParseException($this->errorSnippet());
            } elseif (($this->ch < "\x20" && $this->ch !== "\t") || $this->ch === "\x7F") {
                throw new ParseException($this->errorSnippet());
            } else {
                $str .= $this->ch;
            }
        }
        $this->next();
        $end = $this->here();
        $raw = \substr($this->source, $start->offset, $end->offset - $start->offset);
        return new StringNode($str, $raw, new Span($start, $end));
    }

    private function parseRawString(): ?RawStringNode
    {
        if ($this->ch !== '"' || $this->lookahead(2) !== '""') {
            return null;
        }
        $start = $this->here();
        $this->next();
        $this->next();
        $this->next();
        $hasLeadingNewline = false;
        if ($this->ch === "\r" && $this->lookahead(1) === "\n") {
            $this->next();
        }
        if ($this->ch === "\n") {
            $hasLeadingNewline = true;
            $this->next();
        }

        $str = '';
        while (!$this->done) {
            if ($this->ch === '"' && $this->lookahead(2) === '""') {
                $this->next();
                $this->next();
                $this->next();
                if ($str === '' && !$hasLeadingNewline) {
                    throw new ParseException($this->errorSnippet('Raw strings cannot be empty'));
                }
                $end = $this->here();
                $raw = \substr($this->source, $start->offset, $end->offset - $start->offset);
                return new RawStringNode($str, $raw, new Span($start, $end));
            }
            $str .= $this->ch;
            $this->next();
        }
        throw new ParseException($this->errorSnippet());
    }

    private function parseNumber(): IntegerNode|FloatNode|null
    {
        if (!$this->isDigit($this->ch) && $this->ch !== '-') {
            return null;
        }
        $start = $this->here();
        $numStr = '';
        $float = false;
        if ($this->ch === '-') {
            $numStr .= $this->ch;
            $this->next();
            if (!$this->isDigit($this->ch)) {
                throw new ParseException($this->errorSnippet());
            }
        }
        if ($this->ch === '0') {
            $numStr .= $this->ch;
            $this->next();
        } else {
            while ($this->isDigit($this->ch)) {
                $numStr .= $this->ch;
                $this->next();
            }
        }
        if ($this->ch === '.') {
            $float = true;
            $numStr .= $this->ch;
            $this->next();
            if (!$this->isDigit($this->ch)) {
                throw new ParseException($this->errorSnippet());
            }
            while ($this->isDigit($this->ch)) {
                $numStr .= $this->ch;
                $this->next();
            }
        }
        if ($this->ch === 'e' || $this->ch === 'E') {
            $float = true;
            $numStr .= $this->ch;
            $this->next();
            if ($this->ch === '+' || $this->ch === '-') {
                $numStr .= $this->ch;
                $this->next();
            }
            if (!$this->isDigit($this->ch)) {
                throw new ParseException($this->errorSnippet());
            }
            while ($this->isDigit($this->ch)) {
                $numStr .= $this->ch;
                $this->next();
            }
        }
        $end = $this->here();
        $span = new Span($start, $end);
        if ($float) {
            return new FloatNode((float) $numStr, $numStr, $span);
        }
        $num = $this->toSafeNumber($numStr);
        if (\is_float($num)) {
            return new FloatNode($num, $numStr, $span);
        }
        return new IntegerNode($num, $numStr, $span);
    }

    private function parseObject(): ?ObjectNode
    {
        if ($this->ch !== '{') {
            return null;
        }
        $start = $this->here();
        $this->next();
        $this->skipWhitespace();
        $properties = [];
        $seen = [];
        if ($this->ch === '}') {
            $this->next();
            $end = $this->here();
            return new ObjectNode($properties, new Span($start, $end));
        }
        while (true) {
            $keyStart = $this->here();
            if ($this->ch === '"') {
                $key = $this->parseString();
                if ($key === null) {
                    throw new ParseException($this->errorSnippet());
                }
            } else {
                $key = $this->parseKey();
            }
            if (isset($seen[$key->value])) {
                $this->pos = $keyStart->offset + 1;
                throw new ParseException(
                    $this->errorSnippet('Duplicate key ' . \json_encode($key->value)),
                );
            }
            $seen[$key->value] = true;
            $this->skipWhitespace();
            if ($this->ch !== ':') {
                throw new ParseException($this->errorSnippet());
            }
            $this->next();
            $value = $this->parseValue();
            $this->expectValue($value);
            $propSpan = new Span($keyStart, $value->span->end);
            $properties[] = new Property($key, $value, $propSpan);
            $newlineAfterValue = $this->skipWhitespace();
            if ($this->ch === '}') {
                $this->next();
                $end = $this->here();
                return new ObjectNode($properties, new Span($start, $end));
            } elseif ($this->ch === ',') {
                $this->next();
                $this->skipWhitespace();
                if ($this->ch === '}') {
                    $this->next();
                    $end = $this->here();
                    return new ObjectNode($properties, new Span($start, $end));
                }
            } elseif ($newlineAfterValue) {
                continue;
            } else {
                throw new ParseException(
                    $this->errorSnippet('Expected comma or newline between key-value pairs'),
                );
            }
        }
    }

    private function parseKey(): IdentifierKey
    {
        $start = $this->here();
        $identifier = '';
        while ($this->isKeyChar($this->ch)) {
            $identifier .= $this->ch;
            $this->next();
        }
        if ($identifier === '') {
            throw new ParseException($this->errorSnippet());
        }
        $end = $this->here();
        return new IdentifierKey($identifier, new Span($start, $end));
    }

    private function parseArray(): ?ArrayNode
    {
        if ($this->ch !== '[') {
            return null;
        }
        $start = $this->here();
        $this->next();
        $this->skipWhitespace();
        $elements = [];
        if ($this->ch === ']') {
            $this->next();
            $end = $this->here();
            return new ArrayNode($elements, new Span($start, $end));
        }
        while (true) {
            $value = $this->parseValue();
            $this->expectValue($value);
            $elements[] = new Element($value);
            $newLineAfterValue = $this->skipWhitespace();
            if ($this->ch === ']') {
                $this->next();
                $end = $this->here();
                return new ArrayNode($elements, new Span($start, $end));
            } elseif ($this->ch === ',') {
                $this->next();
                $this->skipWhitespace();
                if ($this->ch === ']') {
                    $this->next();
                    $end = $this->here();
                    return new ArrayNode($elements, new Span($start, $end));
                }
            } elseif ($newLineAfterValue) {
                continue;
            } else {
                throw new ParseException(
                    $this->errorSnippet('Expected comma or newline between values'),
                );
            }
        }
    }

    private function parseKeyword(string $name): BooleanNode|NullNode|null
    {
        if ($this->ch !== $name[0]) {
            return null;
        }
        $start = $this->here();
        for ($i = 1; $i < \strlen($name); $i++) {
            $this->next();
            if ($this->ch !== $name[$i]) {
                throw new ParseException($this->errorSnippet());
            }
        }
        $this->next();
        if (
            $this->isWhitespace($this->ch)
            || $this->ch === ','
            || $this->ch === '}'
            || $this->ch === ']'
            || $this->done
        ) {
            $end = $this->here();
            $span = new Span($start, $end);
            if ($name === 'null') {
                return new NullNode($span);
            }
            return new BooleanNode($name === 'true', $span);
        }
        throw new ParseException($this->errorSnippet());
    }

    private function skipWhitespace(): bool
    {
        $hasNewline = false;
        while ($this->isWhitespace($this->ch)) {
            $hasNewline = $hasNewline || $this->ch === "\n";
            $this->next();
        }
        $hasNewlineAfterComment = $this->skipComment();
        return $hasNewline || $hasNewlineAfterComment;
    }

    private function skipComment(): bool
    {
        if ($this->ch === '#') {
            $start = $this->here();
            $text = '';
            $this->next(); // skip '#'
            while (!$this->done && $this->ch !== "\n") {
                $text .= $this->ch;
                $this->next();
            }
            $end = $this->here();
            $this->comments[] = new CommentNode($text, new Span($start, $end));
            return $this->skipWhitespace();
        }
        return false;
    }

    private function isWhitespace(string $ch): bool
    {
        return $ch === ' ' || $ch === "\n" || $ch === "\t" || $ch === "\r";
    }

    private function isHexDigit(string $ch): bool
    {
        return ($ch >= '0' && $ch <= '9') || ($ch >= 'A' && $ch <= 'F');
    }

    private function isDigit(string $ch): bool
    {
        return $ch >= '0' && $ch <= '9';
    }

    private function isKeyChar(string $ch): bool
    {
        return ($ch >= 'A' && $ch <= 'Z')
            || ($ch >= 'a' && $ch <= 'z')
            || ($ch >= '0' && $ch <= '9')
            || $ch === '_'
            || $ch === '-';
    }

    private function toSafeNumber(string $str): int|float
    {
        if ($str === '-0') {
            return -0.0;
        }
        $int = (int) $str;
        if ((string) $int === $str) {
            return $int;
        }
        throw new ParseException($this->errorSnippet('Integer overflow'));
    }

    /**
     * @phpstan-assert !null $value
     */
    private function expectValue(mixed $value): void
    {
        if ($value === null) {
            throw new ParseException($this->errorSnippet());
        }
    }

    private static function codePointToUtf8(int $cp): string
    {
        return \mb_chr($cp, 'UTF-8');
    }

    private function formatChar(string $ch): string
    {
        if ($ch === '') {
            return '""';
        }
        $ord = \ord($ch);
        if ($ord < 0x80) {
            return (string) \json_encode($ch, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        }
        $bytePos = $this->pos - 1;
        $byte = \ord($this->source[$bytePos]);
        $len = match (true) {
            $byte < 0xC0 => 1,
            $byte < 0xE0 => 2,
            $byte < 0xF0 => 3,
            default => 4,
        };
        $fullChar = \substr($this->source, $bytePos, $len);
        return (string) \json_encode($fullChar, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function errorSnippet(string $message = ''): string
    {
        $customMessage = $message !== '';
        if (!$customMessage) {
            if ($this->ch === '' || $this->done) {
                $message = 'Unexpected end of input';
            } else {
                $message = 'Unexpected character ' . $this->formatChar($this->ch);
            }
        }
        if (!$customMessage && $this->ch === '' && $this->done) {
            $message = 'Unexpected end of input';
        }

        $start = \max(0, $this->pos - 40);
        $pre = \substr($this->source, $start, $this->pos - $start);
        $lines = \explode("\n", $pre);
        $lastLine = \end($lines) ?: '';
        $postParts = \explode("\n", \substr($this->source, $this->pos, 40), 2);
        $postfix = $postParts[0];

        if ($lastLine === '') {
            $count = \count($lines);
            $lastLine = ($count >= 2) ? $lines[$count - 2] : '';
            $lastLine .= ' ';
            $this->lineNumber--;
            $postfix = '';
        }

        $snippet = "    {$lastLine}{$postfix}\n";
        $pointer = '    ' . \str_repeat('.', \max(0, \strlen($lastLine) - 1)) . "^\n";
        return "{$message} on line {$this->lineNumber}.\n\n{$snippet}{$pointer}";
    }

    // ---- Comment attachment ----

    /**
     * @param CommentNode[] $comments
     */
    private static function attachComments(Document $doc, array $comments, string $source): void
    {
        if (\count($comments) === 0) {
            return;
        }

        $valueStart = $doc->value->span->start->offset;
        $valueEnd = $doc->value->span->end->offset;

        $inside = [];
        foreach ($comments as $c) {
            if ($c->span->start->offset < $valueStart) {
                $doc->leadingComments[] = $c;
            } elseif ($c->span->start->offset >= $valueEnd) {
                $doc->danglingComments[] = $c;
            } else {
                $inside[] = $c;
            }
        }

        if (\count($inside) > 0) {
            self::distributeComments($doc->value, $inside, $source);
        }
    }

    /**
     * @param CommentNode[] $comments
     */
    private static function distributeComments(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        array $comments,
        string $source,
    ): void {
        if ($node instanceof ObjectNode) {
            self::distributeToObject($node, $comments, $source);
        } elseif ($node instanceof ArrayNode) {
            self::distributeToArray($node, $comments, $source);
        }
    }

    /**
     * @param CommentNode[] $comments
     */
    private static function distributeToObject(ObjectNode $node, array $comments, string $source): void
    {
        $props = $node->properties;

        if (\count($props) === 0) {
            $node->danglingComments = $comments;
            return;
        }

        foreach ($comments as $c) {
            // Check if comment is inside a nested value
            $nested = false;
            foreach ($props as $prop) {
                if (
                    $c->span->start->offset >= $prop->value->span->start->offset
                    && $c->span->start->offset < $prop->value->span->end->offset
                ) {
                    self::distributeComments($prop->value, [$c], $source);
                    $nested = true;
                    break;
                }
            }
            if ($nested) {
                continue;
            }

            // Try to attach as trailing comment (on same line as a property's value)
            $attached = false;
            foreach ($props as $prop) {
                if (
                    $c->span->start->offset > $prop->value->span->end->offset
                    && !self::hasNewlineBetween(
                        $source,
                        $prop->value->span->start->offset,
                        $c->span->start->offset,
                    )
                ) {
                    $prop->trailingComment = $c;
                    $attached = true;
                    break;
                }
            }
            if ($attached) {
                continue;
            }

            // Try to attach as leading comment (before a property's key)
            foreach ($props as $prop) {
                if ($c->span->start->offset < $prop->key->span->start->offset) {
                    $prop->leadingComments[] = $c;
                    $attached = true;
                    break;
                }
            }
            if ($attached) {
                continue;
            }

            // Dangling comment (after last property, before closing brace)
            $node->danglingComments[] = $c;
        }
    }

    /**
     * @param CommentNode[] $comments
     */
    private static function distributeToArray(ArrayNode $node, array $comments, string $source): void
    {
        $elements = $node->elements;

        if (\count($elements) === 0) {
            $node->danglingComments = $comments;
            return;
        }

        foreach ($comments as $c) {
            // Check if comment is inside a nested value
            $nested = false;
            foreach ($elements as $el) {
                if (
                    $c->span->start->offset >= $el->value->span->start->offset
                    && $c->span->start->offset < $el->value->span->end->offset
                ) {
                    self::distributeComments($el->value, [$c], $source);
                    $nested = true;
                    break;
                }
            }
            if ($nested) {
                continue;
            }

            // Try to attach as trailing comment (on same line as element's value)
            $attached = false;
            foreach ($elements as $el) {
                if (
                    $c->span->start->offset > $el->value->span->end->offset
                    && !self::hasNewlineBetween(
                        $source,
                        $el->value->span->start->offset,
                        $c->span->start->offset,
                    )
                ) {
                    $el->trailingComment = $c;
                    $attached = true;
                    break;
                }
            }
            if ($attached) {
                continue;
            }

            // Try to attach as leading comment (before next element)
            foreach ($elements as $el) {
                if ($c->span->start->offset < $el->value->span->start->offset) {
                    $el->leadingComments[] = $c;
                    $attached = true;
                    break;
                }
            }
            if ($attached) {
                continue;
            }

            // Dangling comment (after last element, before closing bracket)
            $node->danglingComments[] = $c;
        }
    }

    // ---- Blank line detection ----

    private static function attachBlankLines(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        string $source,
    ): void {
        if ($node instanceof ObjectNode) {
            $props = $node->properties;
            for ($i = 0; $i < \count($props); $i++) {
                $regionStart = $i === 0
                    ? $node->span->start->offset + 1
                    : ($props[$i - 1]->trailingComment !== null
                        ? $props[$i - 1]->trailingComment->span->end->offset
                        : $props[$i - 1]->span->end->offset);

                $regionEnd = \count($props[$i]->leadingComments) > 0
                    ? $props[$i]->leadingComments[0]->span->start->offset
                    : $props[$i]->key->span->start->offset;

                $props[$i]->emptyLineBefore = self::hasBlankLine($source, $regionStart, $regionEnd);
                self::attachBlankLines($props[$i]->value, $source);
            }
        } elseif ($node instanceof ArrayNode) {
            $elements = $node->elements;
            for ($i = 0; $i < \count($elements); $i++) {
                $regionStart = $i === 0
                    ? $node->span->start->offset + 1
                    : ($elements[$i - 1]->trailingComment !== null
                        ? $elements[$i - 1]->trailingComment->span->end->offset
                        : $elements[$i - 1]->value->span->end->offset);

                $regionEnd = \count($elements[$i]->leadingComments) > 0
                    ? $elements[$i]->leadingComments[0]->span->start->offset
                    : $elements[$i]->value->span->start->offset;

                $elements[$i]->emptyLineBefore = self::hasBlankLine($source, $regionStart, $regionEnd);
                self::attachBlankLines($elements[$i]->value, $source);
            }
        }
    }

    private static function hasNewlineBetween(string $source, int $from, int $to): bool
    {
        for ($i = $from; $i < $to; $i++) {
            if ($source[$i] === "\n") {
                return true;
            }
        }
        return false;
    }

    private static function hasBlankLine(string $source, int $from, int $to): bool
    {
        $afterNewline = false;
        for ($i = $from; $i < $to; $i++) {
            if ($source[$i] === "\n") {
                if ($afterNewline) {
                    return true;
                }
                $afterNewline = true;
            } elseif ($source[$i] !== ' ' && $source[$i] !== "\t" && $source[$i] !== "\r") {
                $afterNewline = false;
            }
        }
        return false;
    }
}
