<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Maml;
use Maml\ParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function parseProvider(): array
    {
        return self::loadTestCases('parse.test.txt');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function errorProvider(): array
    {
        return self::loadTestCases('error.test.txt');
    }

    #[DataProvider('parseProvider')]
    public function testParse(string $name, string $input, string $expected): void
    {
        if ($name === 'integer negative zero') {
            $result = Maml::parse($input);
            $this->assertSame(-0.0, $result);
            $this->assertSame('-0', (string) $result);
            return;
        }

        $result = Maml::parse($input);
        $expectedValue = json_decode($expected, true);
        $this->assertSame($expectedValue, $result);
    }

    #[DataProvider('errorProvider')]
    public function testError(string $name, string $input, string $expected): void
    {
        $expectedError = self::trimLines($expected);

        if ($name === 'emoji in identifier key') {
            $this->expectException(ParseException::class);
            Maml::parse($input);
            return;
        }

        $this->expectException(ParseException::class);
        try {
            Maml::parse($input);
        } catch (ParseException $e) {
            $actual = self::trimLines($e->getMessage());
            $this->assertStringContainsString($expectedError, $actual);
            throw $e;
        }
    }

    public function testExample(): void
    {
        $result = Maml::parse('
{
  project: "MAML"
  tags: [
    "minimal"
    "readable"
  ]

  # A simple nested object
  spec: {
    version: 1
    author: "Anton Medvedev"
  }

  # Array of objects with nested objects
  examples: [
    {
      json: {
        name: "JSON"
        born: 2001
      }
    }
    {
      maml: {
        name: "MAML"
        born: 2025
      }
    }
  ]

  notes: """
This is a raw multiline strings.
Keeps formatting as-is.
"""
}
');
        $this->assertIsArray($result);
        $this->assertSame('MAML', $result['project']);
        $this->assertSame(['minimal', 'readable'], $result['tags']);
        /** @var array<string, mixed> $spec */
        $spec = $result['spec'];
        $this->assertSame(1, $spec['version']);
    }

    public function testLargeInteger(): void
    {
        $result = Maml::parse('9007199254740992');
        $this->assertSame(9007199254740992, $result);
    }

    public function testRawStringWithCRLF(): void
    {
        $result = Maml::parse("\"\"\"line1\r\nline2\r\nline3\"\"\"");
        $this->assertSame("line1\r\nline2\r\nline3", $result);
    }

    public function testRawStringWithMixedNewlines(): void
    {
        $result = Maml::parse("\"\"\"line1\r\nline2\nline3\r\n\"\"\"");
        $this->assertSame("line1\r\nline2\nline3\r\n", $result);
    }

    public function testRawStringWithCRInsideAndCRLFNewline(): void
    {
        $result = Maml::parse("\"\"\"the \r char\r\n\"\"\"");
        $this->assertSame("the \r char\r\n", $result);
    }

    public function testRawStringWithCRAtTheEnd(): void
    {
        $result = Maml::parse("\"\"\"string\r\"\"\"");
        $this->assertSame("string\r", $result);
    }

    public function testRawStringWithLeadingLF(): void
    {
        $result = Maml::parse("\"\"\"\nstring\r\n\"\"\"");
        $this->assertSame("string\r\n", $result);
    }

    public function testRawStringWithLeadingCRLF(): void
    {
        $result = Maml::parse("\"\"\"\r\nstring\r\n\"\"\"");
        $this->assertSame("string\r\n", $result);
    }

    public function testRawStringWithLeadingCR(): void
    {
        $result = Maml::parse("\"\"\"\rstring\r\n\"\"\"");
        $this->assertSame("\rstring\r\n", $result);
    }

    public function testIntegerOverflow(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Integer overflow');
        Maml::parse('99999999999999999999');
    }

    public function testSurrogateCodePointsRejected(): void
    {
        // Surrogate code points are not valid Unicode scalar values
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('out of range');
        Maml::parse('"\u{D800}"');
    }

    public function testLowSurrogateRejected(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('out of range');
        Maml::parse('"\u{DC00}"');
    }

    public function testHighSurrogateEndOfRange(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('out of range');
        Maml::parse('"\u{DBFF}"');
    }

    public function testLowSurrogateEndOfRange(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('out of range');
        Maml::parse('"\u{DFFF}"');
    }

    public function testUnicodeScalarValueBoundaries(): void
    {
        $this->assertSame(mb_chr(0x0000, 'UTF-8'), Maml::parse('"\u{0}"'));
        $this->assertSame(mb_chr(0xD7FF, 'UTF-8'), Maml::parse('"\u{D7FF}"'));
        $this->assertSame(mb_chr(0xE000, 'UTF-8'), Maml::parse('"\u{E000}"'));
        $this->assertSame(mb_chr(0xFFFF, 'UTF-8'), Maml::parse('"\u{FFFF}"'));
        $this->assertSame(mb_chr(0x10000, 'UTF-8'), Maml::parse('"\u{10000}"'));
        $this->assertSame(mb_chr(0x10FFFF, 'UTF-8'), Maml::parse('"\u{10FFFF}"'));
    }

    public function testStringAllowsLiteralTab(): void
    {
        $result = Maml::parse("\"\thello\tworld\t\"");
        $this->assertSame("\thello\tworld\t", $result);
    }

    public function testAllControlCharactersBelowU0020RejectedExceptTab(): void
    {
        for ($code = 0; $code < 0x20; $code++) {
            if ($code === 0x09) {
                continue;
            } // tab is allowed
            try {
                Maml::parse('"' . chr($code) . '"');
                $this->fail('Expected ParseException for control character 0x' . dechex($code));
            } catch (ParseException $e) {
                $this->assertInstanceOf(ParseException::class, $e);
            }
        }
    }

    public function testUnescapedDelInsideString(): void
    {
        $this->expectException(ParseException::class);
        Maml::parse("\"\x7F\"");
    }

    public function testUnterminatedString(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected end of input');
        Maml::parse('"unterminated');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    private static function loadTestCases(string $filename): array
    {
        $content = (string) file_get_contents(__DIR__ . '/fixtures/' . $filename);
        $cases = explode('===', $content);
        $result = [];
        foreach ($cases as $case) {
            $case = trim($case);
            if ($case === '') {
                continue;
            }
            $lines = explode("\n", $case, 2);
            $name = trim($lines[0]);
            [$input, $expected] = explode('---', $lines[1], 2);
            $result[$name] = [$name, $input, $expected];
        }
        return $result;
    }

    private static function trimLines(string $x): string
    {
        $lines = explode("\n", $x);
        $lines = array_map('rtrim', $lines);
        return trim(implode("\n", $lines));
    }
}
