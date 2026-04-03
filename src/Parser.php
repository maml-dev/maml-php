<?php

declare(strict_types=1);

namespace Maml;

final class Parser
{
    private const NOT_MATCHED = "\x00__NOT_MATCHED__";

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
    private string $ch = '';
    private bool $done = false;

    private function __construct(string $source)
    {
        $this->source = $source;
        $this->next();
    }

    public static function parse(string $source): mixed
    {
        $parser = new self($source);
        $value = $parser->parseValue();
        $parser->skipWhitespace();

        if (!$parser->done) {
            throw new ParseException($parser->errorSnippet());
        }

        $parser->expectValue($value);
        return $value;
    }

    private function next(): void
    {
        if ($this->pos < \strlen($this->source)) {
            $this->ch = $this->source[$this->pos];
            $this->pos++;
        } else {
            $this->ch = '';
            $this->done = true;
        }
        if ($this->ch === "\n") {
            $this->lineNumber++;
        }
    }

    private function lookahead(int $n): string
    {
        return \substr($this->source, $this->pos, $n);
    }

    private function parseValue(): mixed
    {
        $this->skipWhitespace();

        $v = $this->parseRawString();
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseString();
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseNumber();
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseObject();
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseArray();
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseKeyword('true', true);
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseKeyword('false', false);
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        $v = $this->parseKeyword('null', null);
        if ($v !== self::NOT_MATCHED) {
            return $v;
        }

        return self::NOT_MATCHED;
    }

    private function parseString(): mixed
    {
        if ($this->ch !== '"') {
            return self::NOT_MATCHED;
        }
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
        return $str;
    }

    private function parseRawString(): mixed
    {
        if ($this->ch !== '"' || $this->lookahead(2) !== '""') {
            return self::NOT_MATCHED;
        }
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
                return $str;
            }
            $str .= $this->ch;
            $this->next();
        }
        throw new ParseException($this->errorSnippet());
    }

    private function parseNumber(): mixed
    {
        if (!$this->isDigit($this->ch) && $this->ch !== '-') {
            return self::NOT_MATCHED;
        }
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
        return $float ? (float) $numStr : $this->toSafeNumber($numStr);
    }

    private function parseObject(): mixed
    {
        if ($this->ch !== '{') {
            return self::NOT_MATCHED;
        }
        $this->next();
        $this->skipWhitespace();
        $obj = [];
        if ($this->ch === '}') {
            $this->next();
            return $obj;
        }
        while (true) {
            $keyPos = $this->pos;
            if ($this->ch === '"') {
                /** @var string $key */
                $key = $this->parseString();
            } else {
                $key = $this->parseKey();
            }
            if (\array_key_exists($key, $obj)) {
                $this->pos = $keyPos;
                throw new ParseException(
                    $this->errorSnippet('Duplicate key ' . \json_encode($key)),
                );
            }
            $this->skipWhitespace();
            if ($this->ch !== ':') {
                throw new ParseException($this->errorSnippet());
            }
            $this->next();
            $value = $this->parseValue();
            $this->expectValue($value);
            $obj[$key] = $value;
            $newlineAfterValue = $this->skipWhitespace();
            if ($this->ch === '}') {
                $this->next();
                return $obj;
            } elseif ($this->ch === ',') {
                $this->next();
                $this->skipWhitespace();
                if ($this->ch === '}') {
                    $this->next();
                    return $obj;
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

    private function parseKey(): string
    {
        $identifier = '';
        while ($this->isKeyChar($this->ch)) {
            $identifier .= $this->ch;
            $this->next();
        }
        if ($identifier === '') {
            throw new ParseException($this->errorSnippet());
        }
        return $identifier;
    }

    private function parseArray(): mixed
    {
        if ($this->ch !== '[') {
            return self::NOT_MATCHED;
        }
        $this->next();
        $this->skipWhitespace();
        $array = [];
        if ($this->ch === ']') {
            $this->next();
            return $array;
        }
        while (true) {
            $value = $this->parseValue();
            $this->expectValue($value);
            $array[] = $value;
            $newLineAfterValue = $this->skipWhitespace();
            if ($this->ch === ']') {
                $this->next();
                return $array;
            } elseif ($this->ch === ',') {
                $this->next();
                $this->skipWhitespace();
                if ($this->ch === ']') {
                    $this->next();
                    return $array;
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

    private function parseKeyword(string $name, mixed $value): mixed
    {
        if ($this->ch !== $name[0]) {
            return self::NOT_MATCHED;
        }
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
            return $value;
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
            while (!$this->done && $this->ch !== "\n") {
                $this->next();
            }
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

    private function expectValue(mixed $value): void
    {
        if ($value === self::NOT_MATCHED) {
            throw new ParseException($this->errorSnippet());
        }
    }

    private static function codePointToUtf8(int $cp): string
    {
        // Code point is guaranteed to be a valid Unicode scalar value
        // (validated before this method is called).
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
        // Multi-byte UTF-8: extract full character from source at current position
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
            // error at "\n"
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
}
