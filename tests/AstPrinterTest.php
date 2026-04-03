<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Maml;
use PHPUnit\Framework\TestCase;

final class AstPrinterTest extends TestCase
{
    // ---- Value round-trips ----

    public function testPrintInteger(): void
    {
        $this->assertPrintRoundTrip('42');
    }

    public function testPrintNegativeInteger(): void
    {
        $this->assertPrintRoundTrip('-100');
    }

    public function testPrintFloat(): void
    {
        $this->assertPrintRoundTrip('3.14');
    }

    public function testPrintFloatWithExponent(): void
    {
        $this->assertPrintRoundTrip('1e6');
    }

    public function testPrintNegativeZero(): void
    {
        $this->assertPrintRoundTrip('-0');
    }

    public function testPrintString(): void
    {
        $this->assertPrintRoundTrip('"hello"');
    }

    public function testPrintStringWithEscapes(): void
    {
        $this->assertPrintRoundTrip('"a\\nb"');
    }

    public function testPrintRawString(): void
    {
        $this->assertPrintRoundTrip('"""hello"""');
    }

    public function testPrintRawStringMultiline(): void
    {
        $this->assertPrintRoundTrip("\"\"\"\nline1\nline2\n\"\"\"");
    }

    public function testPrintTrue(): void
    {
        $this->assertPrintRoundTrip('true');
    }

    public function testPrintFalse(): void
    {
        $this->assertPrintRoundTrip('false');
    }

    public function testPrintNull(): void
    {
        $this->assertPrintRoundTrip('null');
    }

    public function testPrintEmptyArray(): void
    {
        $this->assertPrintRoundTrip('[]');
    }

    public function testPrintEmptyObject(): void
    {
        $this->assertPrintRoundTrip('{}');
    }

    // ---- Container formatting ----

    public function testPrintArray(): void
    {
        $input = "[\n  1\n  2\n  3\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintObject(): void
    {
        $input = "{\n  a: 1\n  b: 2\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintNestedObject(): void
    {
        $input = "{\n  a: {\n    b: 1\n  }\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintNestedArray(): void
    {
        $input = "[\n  [\n    1\n    2\n  ]\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintQuotedKey(): void
    {
        $input = "{\n  \"special key\": 1\n}";
        $this->assertPrintRoundTrip($input);
    }

    // ---- Comment preservation ----

    public function testPrintDocumentLeadingComment(): void
    {
        $input = "# header\n42";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintDocumentDanglingComment(): void
    {
        $input = "42\n# end";
        $doc = Maml::parseAst($input);
        $result = Maml::printAst($doc);
        $this->assertSame($input, $result);
    }

    public function testPrintObjectPropertyComments(): void
    {
        $input = "{\n  # comment\n  a: 1\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintObjectTrailingComment(): void
    {
        $input = "{\n  a: 1 # inline\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintObjectDanglingComment(): void
    {
        $input = "{\n  a: 1\n  # end\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintArrayElementComments(): void
    {
        $input = "[\n  # first\n  1\n  2\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintArrayTrailingComment(): void
    {
        $input = "[\n  1 # one\n  2 # two\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintArrayDanglingComment(): void
    {
        $input = "[\n  1\n  # end\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintEmptyObjectWithComment(): void
    {
        $input = "{\n  # empty\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintEmptyArrayWithComment(): void
    {
        $input = "[\n  # empty\n]";
        $this->assertPrintRoundTrip($input);
    }

    // ---- Blank line preservation ----

    public function testPrintObjectBlankLine(): void
    {
        $input = "{\n  a: 1\n\n  b: 2\n}";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintArrayBlankLine(): void
    {
        $input = "[\n  1\n\n  2\n]";
        $this->assertPrintRoundTrip($input);
    }

    public function testPrintBlankLineWithComments(): void
    {
        $input = "{\n  a: 1\n\n  # section\n  b: 2\n}";
        $this->assertPrintRoundTrip($input);
    }

    // ---- Print ValueNode directly (not Document) ----

    public function testPrintValueNodeDirectly(): void
    {
        $doc = Maml::parseAst('42');
        $result = Maml::printAst($doc->value);
        $this->assertSame('42', $result);
    }

    public function testPrintObjectNodeDirectly(): void
    {
        $doc = Maml::parseAst("{\n  a: 1\n}");
        $result = Maml::printAst($doc->value);
        $this->assertSame("{\n  a: 1\n}", $result);
    }

    private function assertPrintRoundTrip(string $input): void
    {
        $doc = Maml::parseAst($input);
        $result = Maml::printAst($doc);
        $this->assertSame($input, $result);
    }
}
