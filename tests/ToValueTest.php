<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Maml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ToValueTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function parseProvider(): array
    {
        return self::loadTestCases('parse.test.txt');
    }

    #[DataProvider('parseProvider')]
    public function testToValueMatchesParse(string $name, string $input, string $expected): void
    {
        if ($name === 'integer negative zero') {
            $ast = Maml::parseAst($input);
            $result = Maml::toValue($ast);
            $this->assertSame(-0.0, $result);
            $this->assertSame('-0', (string) $result);
            return;
        }

        $plain = Maml::parse($input);
        $ast = Maml::parseAst($input);
        $fromAst = Maml::toValue($ast);
        $this->assertSame($plain, $fromAst, "toValue(parseAst()) should match parse() for case: $name");
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    private static function loadTestCases(string $filename): array
    {
        $content = file_get_contents(__DIR__ . '/fixtures/' . $filename);
        $cases = explode('===', $content);
        $result = [];
        foreach ($cases as $case) {
            $case = trim($case);
            if ($case === '') continue;
            $lines = explode("\n", $case, 2);
            $name = trim($lines[0]);
            [$input, $expected] = explode('---', $lines[1], 2);
            $result[$name] = [$name, $input, $expected];
        }
        return $result;
    }
}
