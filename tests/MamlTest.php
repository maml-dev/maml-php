<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Maml;
use PHPUnit\Framework\TestCase;

final class MamlTest extends TestCase
{
    public function testParseAndStringify(): void
    {
        $source = '{foo: "bar", nums: [1, 2, 3]}';
        $parsed = Maml::parse($source);

        $this->assertSame(['foo' => 'bar', 'nums' => [1, 2, 3]], $parsed);

        $stringified = Maml::stringify($parsed);
        $this->assertIsString($stringified);

        $reparsed = Maml::parse($stringified);
        $this->assertSame($parsed, $reparsed);
    }
}
