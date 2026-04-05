<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Ast\ObjectNode;
use Maml\Ast\Position;
use Maml\Ast\Span;
use Maml\Maml;
use PHPUnit\Framework\TestCase;

final class ErrorSnippetTest extends TestCase
{
    // ---- Position (single ^) ----

    public function testPositionAtStartOfSource(): void
    {
        $result = Maml::errorSnippet('null', new Position(0, 1, 1), 'Expected object');
        $this->assertSame(
            "Expected object on line 1.\n\n    null\n    ^\n",
            $result,
        );
    }

    public function testPositionMiddleOfLine(): void
    {
        $source = '{host: "localhost"}';
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $val->span->start, 'Bad value');
        $this->assertSame(
            "Bad value on line 1.\n\n    {host: \"localhost\"}\n           ^\n",
            $result,
        );
    }

    public function testPositionOnSecondLine(): void
    {
        $source = "{\n  port: \"bad\"\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $val->span->start, 'Expected integer');
        $this->assertSame(
            "Expected integer on line 2.\n\n      port: \"bad\"\n            ^\n",
            $result,
        );
    }

    // ---- Span (^^^^ underline) ----

    public function testSpanUnderlinesSingleToken(): void
    {
        $source = '{port: "bad"}';
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $val->span, 'Expected integer');
        $this->assertSame(
            "Expected integer on line 1.\n\n    {port: \"bad\"}\n           ^^^^^\n",
            $result,
        );
    }

    public function testSpanUnderlinesSingleChar(): void
    {
        $source = '0';
        $doc = Maml::parseAst($source);
        $result = Maml::errorSnippet($source, $doc->value->span, 'Bad');
        $this->assertSame("Bad on line 1.\n\n    0\n    ^\n", $result);
    }

    public function testSpanUnderlineInteger(): void
    {
        $source = "{\n  timeout: -1\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $val->span, 'Must be positive');
        $this->assertSame(
            "Must be positive on line 2.\n\n      timeout: -1\n               ^^\n",
            $result,
        );
    }

    public function testSpanUnderlineKey(): void
    {
        $source = '{badkey: 1}';
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $key = $doc->value->properties[0]->key;

        $result = Maml::errorSnippet($source, $key->span, 'Unknown key');
        $this->assertSame(
            "Unknown key on line 1.\n\n    {badkey: 1}\n     ^^^^^^\n",
            $result,
        );
    }

    public function testSpanUnderlineNestedObject(): void
    {
        // {a: 1, b: 2} is 12 chars: { a : _ 1 , _ b : _ 2 }
        $source = '{x: {a: 1, b: 2}}';
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $inner = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $inner->span, 'Invalid');
        $this->assertSame(
            "Invalid on line 1.\n\n    {x: {a: 1, b: 2}}\n        ^^^^^^^^^^^^\n",
            $result,
        );
    }

    public function testSpanMultiLineUnderlinesToEndOfFirstLine(): void
    {
        $source = "{\n  data: {\n    a: 1\n  }\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;
        // Multi-line object: span starts at { on line 2, ends at } on line 4
        // Line 2 is "  data: {", { is at charCol 8, line length 9, so width = 9 - 8 = 1
        $result = Maml::errorSnippet($source, $val->span, 'Bad shape');
        $this->assertSame(
            "Bad shape on line 2.\n\n      data: {\n            ^\n",
            $result,
        );
    }

    // ---- Context lines ----

    public function testContextZeroShowsOnlyErrorLine(): void
    {
        $source = "line1\nline2\nline3\nline4";
        $pos = new Position(18, 4, 1);
        $result = Maml::errorSnippet($source, $pos, 'Error');
        $this->assertSame("Error on line 4.\n\n    line4\n    ^\n", $result);
    }

    public function testContextShowsPrecedingLines(): void
    {
        $source = "{\n  a: 1\n  b: 2\n  c: 3\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[2]->value;

        $result = Maml::errorSnippet($source, $val->span->start, 'Bad', context: 2);
        $this->assertSame(
            "Bad on line 4.\n\n      a: 1\n      b: 2\n      c: 3\n         ^\n",
            $result,
        );
    }

    public function testContextClampedAtStartOfFile(): void
    {
        $source = "first\nsecond";
        $pos = new Position(6, 2, 1);
        $result = Maml::errorSnippet($source, $pos, 'Error', context: 5);
        // Only 1 context line available (line 1), requesting 5
        $this->assertSame(
            "Error on line 2.\n\n    first\n    second\n    ^\n",
            $result,
        );
    }

    public function testContextWithSpan(): void
    {
        $source = "{\n  a: 1\n  b: \"bad\"\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[1]->value;

        $result = Maml::errorSnippet($source, $val->span, 'Wrong type', context: 1);
        $this->assertSame(
            "Wrong type on line 3.\n\n      a: 1\n      b: \"bad\"\n         ^^^^^\n",
            $result,
        );
    }

    // ---- Truncation (lines > 70 chars) ----

    public function testLongLineNoTruncationAt70(): void
    {
        $line = \str_repeat('x', 70);
        $result = Maml::errorSnippet($line, new Position(0, 1, 1), 'Err');
        $this->assertStringContainsString($line, $result);
        $this->assertStringNotContainsString('…', $result);
    }

    public function testLongLineTruncatedErrorNearStart(): void
    {
        $source = 'abcde' . \str_repeat('x', 80);
        // Point at 'a' (column 1)
        $result = Maml::errorSnippet($source, new Position(0, 1, 1), 'Err');
        $this->assertStringNotContainsString('…a', $result); // no left ellipsis
        $lines = \explode("\n", $result);
        $content = \substr($lines[2], 4); // strip indent
        $this->assertLessThanOrEqual(70, \mb_strlen($content, 'UTF-8'));
        $this->assertStringEndsWith('…', $content);
    }

    public function testLongLineTruncatedWithBothEllipses(): void
    {
        // 120 chars: 55 a's + ERROR + 60 b's
        $source = \str_repeat('a', 55) . 'ERROR' . \str_repeat('b', 60);
        $span = new Span(new Position(55, 1, 56), new Position(60, 1, 61));
        $result = Maml::errorSnippet($source, $span, 'Found it');
        $this->assertStringContainsString('^^^^^', $result);
        $lines = \explode("\n", $result);
        $content = \substr($lines[2], 4);
        $this->assertStringStartsWith('…', $content);
        $this->assertStringEndsWith('…', $content);
        $this->assertLessThanOrEqual(70, \mb_strlen($content, 'UTF-8'));
    }

    public function testLongLineTruncatedErrorAtEnd(): void
    {
        $source = \str_repeat('a', 90) . 'END';
        $span = new Span(new Position(90, 1, 91), new Position(93, 1, 94));
        $result = Maml::errorSnippet($source, $span, 'Bad');
        $this->assertStringContainsString('^^^', $result);
        $lines = \explode("\n", $result);
        $content = \substr($lines[2], 4);
        $this->assertStringStartsWith('…', $content);
        $this->assertLessThanOrEqual(70, \mb_strlen($content, 'UTF-8'));
    }

    public function testLongLineContextLinesTruncatedSameWindow(): void
    {
        $long1 = \str_repeat('x', 100);
        $long2 = \str_repeat('y', 100);
        $source = $long1 . "\n" . $long2;
        $pos = new Position(\strlen($long1) + 1, 2, 1);
        $result = Maml::errorSnippet($source, $pos, 'Err', context: 1);
        $lines = \explode("\n", $result);
        // lines[2] = context line, lines[3] = error line
        $ctx = \mb_strlen(\substr($lines[2], 4), 'UTF-8');
        $err = \mb_strlen(\substr($lines[3], 4), 'UTF-8');
        $this->assertSame($ctx, $err);
    }

    public function testTruncationPreservesPointerAlignment(): void
    {
        // 120 chars: 55 a's + NEEDLE + 60 b's
        $source = \str_repeat('a', 55) . 'NEEDLE' . \str_repeat('b', 59);
        $span = new Span(new Position(55, 1, 56), new Position(61, 1, 62));
        $result = Maml::errorSnippet($source, $span, 'Err');
        $this->assertStringContainsString('^^^^^^', $result);
        // Verify NEEDLE and ^^^^^^ are vertically aligned
        $lines = \explode("\n", $result);
        $contentLine = $lines[2];
        $pointerLine = $lines[3];
        $needlePos = \mb_strpos($contentLine, 'NEEDLE');
        $caretPos = \mb_strpos($pointerLine, '^^^^^^');
        $this->assertNotFalse($needlePos);
        $this->assertNotFalse($caretPos);
        $this->assertSame($needlePos, $caretPos);
    }

    public function testTruncationWideSpanPullsWindowRight(): void
    {
        // A 100-char line with a wide span near the end
        $source = \str_repeat('a', 30) . \str_repeat('B', 50) . \str_repeat('c', 20);
        // Span covers the 50 B's starting at col 31
        $span = new Span(new Position(30, 1, 31), new Position(80, 1, 81));
        $result = Maml::errorSnippet($source, $span, 'Wide');
        // All 50 B's should be underlined
        $this->assertStringContainsString(\str_repeat('^', 50), $result);
    }

    // ---- Edge cases ----

    public function testEmptySource(): void
    {
        $result = Maml::errorSnippet('', new Position(0, 1, 1), 'Empty');
        $this->assertSame("Empty on line 1.\n\n    \n    ^\n", $result);
    }

    public function testSingleCharSource(): void
    {
        $doc = Maml::parseAst('1');
        $result = Maml::errorSnippet('1', $doc->value->span, 'Bad');
        $this->assertSame("Bad on line 1.\n\n    1\n    ^\n", $result);
    }

    public function testErrorOnLastLineNoTrailingNewline(): void
    {
        $result = Maml::errorSnippet("a: 1\nb: 2", new Position(5, 2, 1), 'Err');
        $this->assertSame("Err on line 2.\n\n    b: 2\n    ^\n", $result);
    }

    public function testErrorOnFirstLineOfMultiline(): void
    {
        $result = Maml::errorSnippet("first\nsecond\nthird", new Position(0, 1, 1), 'Err');
        $this->assertSame("Err on line 1.\n\n    first\n    ^\n", $result);
    }

    public function testSpanWidthOneIsSameAsCaret(): void
    {
        $span = new Span(new Position(0, 1, 1), new Position(1, 1, 2));
        $result = Maml::errorSnippet('abc', $span, 'X');
        $this->assertSame("X on line 1.\n\n    abc\n    ^\n", $result);
    }

    public function testSpanCoversEntireLine(): void
    {
        $span = new Span(new Position(0, 1, 1), new Position(5, 1, 6));
        $result = Maml::errorSnippet('hello', $span, 'All');
        $this->assertSame("All on line 1.\n\n    hello\n    ^^^^^\n", $result);
    }

    public function testTabsInSource(): void
    {
        $source = "\tkey: val";
        // "val" starts at byte 6, column 7 (1-based)
        $result = Maml::errorSnippet($source, new Position(6, 1, 7), 'Bad');
        $this->assertStringContainsString("\tkey: val\n", $result);
        $this->assertStringContainsString("      ^\n", $result);
    }

    public function testUnicodeInSourceWithQuotedKey(): void
    {
        // Quoted keys support unicode
        $source = '{"名前": "val"}';
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $val = $doc->value->properties[0]->value;

        $result = Maml::errorSnippet($source, $val->span, 'Wrong');
        $this->assertStringContainsString('^^^^^', $result);
    }

    public function testContextOnLineOne(): void
    {
        $result = Maml::errorSnippet('only', new Position(0, 1, 1), 'Err', context: 3);
        $this->assertSame("Err on line 1.\n\n    only\n    ^\n", $result);
    }

    // ---- Integration with validator errors ----

    public function testValidationErrorWithSnippet(): void
    {
        $source = "{\n  host: \"ok\"\n  port: \"bad\"\n}";
        $doc = Maml::parseAst($source);
        $schema = \Maml\Schema\S::object([
            'host' => \Maml\Schema\S::string(),
            'port' => \Maml\Schema\S::integer(),
        ]);
        $errors = Maml::validate($doc, $schema);
        $this->assertCount(1, $errors);
        $this->assertNotNull($errors[0]->span);

        $result = Maml::errorSnippet($source, $errors[0]->span, $errors[0]->message);
        $this->assertStringContainsString('Expected integer, got string', $result);
        $this->assertStringContainsString('on line 3', $result);
        $this->assertStringContainsString('^', $result);
    }
}
