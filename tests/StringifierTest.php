<?php

declare(strict_types=1);

namespace Maml\Tests;

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
}
