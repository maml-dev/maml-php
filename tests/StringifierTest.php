<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Annotated;
use Maml\Maml;
use PHPUnit\Framework\TestCase;

final class StringifierTest extends TestCase
{
    public function testInt(): void
    {
        $this->assertSame('42', Maml::stringify(42));
    }

    public function testLargeInt(): void
    {
        $this->assertSame('9007199254740992', Maml::stringify(9007199254740992));
    }

    public function testFloat(): void
    {
        $this->assertSame('1.5', Maml::stringify(1.5));
    }

    public function testNegativeZero(): void
    {
        $this->assertSame('-0', Maml::stringify(-0.0));
    }

    public function testBooleanTrue(): void
    {
        $this->assertSame('true', Maml::stringify(true));
    }

    public function testBooleanFalse(): void
    {
        $this->assertSame('false', Maml::stringify(false));
    }

    public function testNull(): void
    {
        $this->assertSame('null', Maml::stringify(null));
    }

    public function testString(): void
    {
        $this->assertSame('"foo"', Maml::stringify('foo'));
    }

    public function testStringWithQuoteAndBackslash(): void
    {
        $this->assertSame('"say \\"hi\\""', Maml::stringify('say "hi"'));
        $this->assertSame('"a\\\\b"', Maml::stringify('a\\b'));
    }

    public function testStringWithTab(): void
    {
        $this->assertSame('"hello\tworld"', Maml::stringify("hello\tworld"));
    }

    public function testStringWithControlCharacters(): void
    {
        $this->assertSame('"\u{0}"', Maml::stringify("\x00"));
        $this->assertSame('"\u{8}"', Maml::stringify("\x08"));
        $this->assertSame('"\u{C}"', Maml::stringify("\x0C"));
        $this->assertSame('"\u{1F}"', Maml::stringify("\x1F"));
        $this->assertSame('"\u{7F}"', Maml::stringify("\x7F"));
    }

    public function testStringWithNewlineAndCarriageReturn(): void
    {
        $this->assertSame('"a\nb"', Maml::stringify("a\nb"));
        $this->assertSame('"a\rb"', Maml::stringify("a\rb"));
    }

    public function testUnicodeScalarValueBoundariesPassThrough(): void
    {
        $d7ff = mb_chr(0xD7FF, 'UTF-8');
        $this->assertSame('"' . $d7ff . '"', Maml::stringify($d7ff));
        $e000 = mb_chr(0xE000, 'UTF-8');
        $this->assertSame('"' . $e000 . '"', Maml::stringify($e000));
        $sup = mb_chr(0x10000, 'UTF-8');
        $this->assertSame('"' . $sup . '"', Maml::stringify($sup));
        $max = mb_chr(0x10FFFF, 'UTF-8');
        $this->assertSame('"' . $max . '"', Maml::stringify($max));
    }

    public function testAllControlCharacters0x01to0x1FExceptTabAreEscaped(): void
    {
        for ($code = 1; $code < 0x20; $code++) {
            if ($code === 0x09) {
                continue;
            } // tab uses \t
            if ($code === 0x0A) {
                continue;
            } // newline uses \n
            if ($code === 0x0D) {
                continue;
            } // CR uses \r
            $result = Maml::stringify(chr($code));
            $this->assertSame('"\u{' . strtoupper(dechex($code)) . '}"', $result);
        }
    }

    public function testArray(): void
    {
        $expected = "[\n  1\n  2\n  3\n]";
        $this->assertSame($expected, Maml::stringify([1, 2, 3]));
    }

    public function testObject(): void
    {
        $expected = "{\n  foo: \"foo\"\n  bar: \"bar\"\n}";
        $this->assertSame($expected, Maml::stringify(['foo' => 'foo', 'bar' => 'bar']));
    }

    public function testObjectWithQuotedKeys(): void
    {
        $expected = "{\n  \"foo bar\": \"value\"\n}";
        $this->assertSame($expected, Maml::stringify(['foo bar' => 'value']));
    }

    public function testEmptyArray(): void
    {
        $this->assertSame('[]', Maml::stringify([]));
    }

    public function testUnsupportedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Maml::stringify(fopen('php://memory', 'r'));
    }

    public function testNestedStructure(): void
    {
        $data = [
            'name' => 'test',
            'items' => [1, 2, 3],
            'nested' => [
                'key' => 'value',
            ],
        ];
        $expected = "{\n  name: \"test\"\n  items: [\n    1\n    2\n    3\n  ]\n  nested: {\n    key: \"value\"\n  }\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    // --- Annotated value tests ---

    public function testLeadingCommentOnObjectProperty(): void
    {
        $data = [
            'host' => 'localhost',
            'port' => Annotated::with(5432)->comment(' Database port'),
        ];
        $expected = "{\n  host: \"localhost\"\n  # Database port\n  port: 5432\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testTrailingCommentOnObjectProperty(): void
    {
        $data = [
            'port' => Annotated::with(5432)->trailingComment(' default'),
        ];
        $expected = "{\n  port: 5432 # default\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testEmptyLineBeforeObjectProperty(): void
    {
        $data = [
            'host' => 'localhost',
            'port' => Annotated::with(5432)->emptyLineBefore(),
        ];
        $expected = "{\n  host: \"localhost\"\n\n  port: 5432\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testEmptyLineBeforeFirstPropertyIsIgnored(): void
    {
        $data = [
            'host' => Annotated::with('localhost')->emptyLineBefore(),
        ];
        $expected = "{\n  host: \"localhost\"\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testMultipleLeadingComments(): void
    {
        $data = [
            'port' => Annotated::with(5432)->comment(' Line 1', ' Line 2'),
        ];
        $expected = "{\n  # Line 1\n  # Line 2\n  port: 5432\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testLeadingCommentOnArrayElement(): void
    {
        $data = [
            Annotated::with('first')->comment(' Primary'),
            'second',
        ];
        $expected = "[\n  # Primary\n  \"first\"\n  \"second\"\n]";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testTrailingCommentOnArrayElement(): void
    {
        $data = [
            Annotated::with('first')->trailingComment(' note'),
            'second',
        ];
        $expected = "[\n  \"first\" # note\n  \"second\"\n]";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testEmptyLineBeforeArrayElement(): void
    {
        $data = [
            'first',
            Annotated::with('second')->emptyLineBefore(),
        ];
        $expected = "[\n  \"first\"\n\n  \"second\"\n]";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDocumentLeadingComment(): void
    {
        $data = Annotated::with([
            'version' => 1,
        ])->comment(' Config file');
        $expected = "# Config file\n{\n  version: 1\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDocumentDanglingComment(): void
    {
        $data = Annotated::with(42)->danglingComment(' end');
        $expected = "42\n# end";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDanglingCommentOnEmptyObject(): void
    {
        $data = [
            'plugins' => Annotated::with([])->danglingComment(' Add plugins here'),
        ];
        $expected = "{\n  plugins: [\n    # Add plugins here\n  ]\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDanglingCommentOnEmptyAssociativeArray(): void
    {
        $obj = new \stdClass();
        // Empty associative array represented via Annotated
        $data = Annotated::with([])->danglingComment(' Empty');
        $expected = "[\n  # Empty\n]";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDanglingCommentOnNonEmptyArray(): void
    {
        $data = Annotated::with([1, 2])->danglingComment(' end of list');
        $expected = "[\n  1\n  2\n  # end of list\n]";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testNestedAnnotatedValues(): void
    {
        $data = [
            'db' => Annotated::with([
                'host' => 'localhost',
                'port' => Annotated::with(5432)->comment(' DB port'),
            ])->comment(' Database section'),
        ];
        $expected = "{\n  # Database section\n  db: {\n    host: \"localhost\"\n    # DB port\n    port: 5432\n  }\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testMixedAnnotatedAndPlainValues(): void
    {
        $data = [
            'a' => 1,
            'b' => Annotated::with(2)->comment(' annotated'),
            'c' => 3,
        ];
        $expected = "{\n  a: 1\n  # annotated\n  b: 2\n  c: 3\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testAnnotatedRootScalar(): void
    {
        $data = Annotated::with(42)->comment(' The answer');
        $expected = "# The answer\n42";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testTrailingCommentOnContainerValueIsIgnored(): void
    {
        $data = [
            'items' => Annotated::with([1, 2])->trailingComment(' ignored'),
        ];
        $expected = "{\n  items: [\n    1\n    2\n  ]\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testCombinedAnnotations(): void
    {
        $data = [
            'host' => 'localhost',
            'port' => Annotated::with(5432)
                ->emptyLineBefore()
                ->comment(' Port setting')
                ->trailingComment(' default'),
        ];
        $expected = "{\n  host: \"localhost\"\n\n  # Port setting\n  port: 5432 # default\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }

    public function testDocumentWithLeadingAndDanglingComments(): void
    {
        $data = Annotated::with([
            'key' => 'value',
        ])->comment(' header')->danglingComment(' footer');
        $expected = "# header\n{\n  key: \"value\"\n  # footer\n}";
        $this->assertSame($expected, Maml::stringify($data));
    }
}
