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
