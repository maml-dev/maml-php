<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Ast\ArrayNode;
use Maml\Ast\BooleanNode;
use Maml\Ast\Document;
use Maml\Ast\FloatNode;
use Maml\Ast\IdentifierKey;
use Maml\Ast\IntegerNode;
use Maml\Ast\NullNode;
use Maml\Ast\ObjectNode;
use Maml\Ast\RawStringNode;
use Maml\Ast\StringNode;
use Maml\Maml;
use Maml\ParseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AstParserTest extends TestCase
{
    // ---- Document ----

    public function testDocumentWrapsValue(): void
    {
        $doc = Maml::parseAst('42');
        $this->assertSame('Document', $doc->type);
        $this->assertInstanceOf(IntegerNode::class, $doc->value);
        $this->assertSame([], $doc->leadingComments);
        $this->assertSame([], $doc->danglingComments);
    }

    public function testDocumentSpanCoversEntireSource(): void
    {
        $doc = Maml::parseAst('42');
        $this->assertSame(0, $doc->span->start->offset);
        $this->assertSame(1, $doc->span->start->line);
        $this->assertSame(1, $doc->span->start->column);
        $this->assertSame(2, $doc->span->end->offset);
        $this->assertSame(1, $doc->span->end->line);
        $this->assertSame(3, $doc->span->end->column);
    }

    // ---- Node types ----

    public function testStringNode(): void
    {
        $node = Maml::parseAst('"hello"')->value;
        $this->assertInstanceOf(StringNode::class, $node);
        $this->assertSame('String', $node->type);
        $this->assertSame('hello', $node->value);
        $this->assertSame('"hello"', $node->raw);
    }

    public function testRawStringNode(): void
    {
        $node = Maml::parseAst('"""hello"""')->value;
        $this->assertInstanceOf(RawStringNode::class, $node);
        $this->assertSame('RawString', $node->type);
        $this->assertSame('hello', $node->value);
        $this->assertSame('"""hello"""', $node->raw);
    }

    public function testIntegerNode(): void
    {
        $node = Maml::parseAst('42')->value;
        $this->assertInstanceOf(IntegerNode::class, $node);
        $this->assertSame('Integer', $node->type);
        $this->assertSame(42, $node->value);
        $this->assertSame('42', $node->raw);
    }

    public function testNegativeIntegerNode(): void
    {
        $node = Maml::parseAst('-100')->value;
        $this->assertInstanceOf(IntegerNode::class, $node);
        $this->assertSame(-100, $node->value);
        $this->assertSame('-100', $node->raw);
    }

    public function testFloatNode(): void
    {
        $node = Maml::parseAst('3.14')->value;
        $this->assertInstanceOf(FloatNode::class, $node);
        $this->assertSame('Float', $node->type);
        $this->assertSame(3.14, $node->value);
        $this->assertSame('3.14', $node->raw);
    }

    public function testFloatWithExponent(): void
    {
        $node = Maml::parseAst('1e6')->value;
        $this->assertInstanceOf(FloatNode::class, $node);
        $this->assertSame(1e6, $node->value);
        $this->assertSame('1e6', $node->raw);
    }

    public function testBooleanTrue(): void
    {
        $node = Maml::parseAst('true')->value;
        $this->assertInstanceOf(BooleanNode::class, $node);
        $this->assertSame('Boolean', $node->type);
        $this->assertTrue($node->value);
    }

    public function testBooleanFalse(): void
    {
        $node = Maml::parseAst('false')->value;
        $this->assertInstanceOf(BooleanNode::class, $node);
        $this->assertFalse($node->value);
    }

    public function testNullNode(): void
    {
        $node = Maml::parseAst('null')->value;
        $this->assertInstanceOf(NullNode::class, $node);
        $this->assertSame('Null', $node->type);
        $this->assertNull($node->value);
    }

    public function testEmptyObject(): void
    {
        $node = Maml::parseAst('{}')->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $this->assertSame('Object', $node->type);
        $this->assertSame([], $node->properties);
    }

    public function testEmptyArray(): void
    {
        $node = Maml::parseAst('[]')->value;
        $this->assertInstanceOf(ArrayNode::class, $node);
        $this->assertSame('Array', $node->type);
        $this->assertSame([], $node->elements);
    }

    // ---- Positions ----

    public function testIntegerAtStart(): void
    {
        $node = Maml::parseAst('42')->value;
        $this->assertSame(0, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(1, $node->span->start->column);
        $this->assertSame(2, $node->span->end->offset);
        $this->assertSame(1, $node->span->end->line);
        $this->assertSame(3, $node->span->end->column);
    }

    public function testIntegerWithLeadingWhitespace(): void
    {
        $node = Maml::parseAst('  42')->value;
        $this->assertSame(2, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(3, $node->span->start->column);
        $this->assertSame(4, $node->span->end->offset);
        $this->assertSame(1, $node->span->end->line);
        $this->assertSame(5, $node->span->end->column);
    }

    public function testStringPositions(): void
    {
        $node = Maml::parseAst('"hi"')->value;
        $this->assertSame(0, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(1, $node->span->start->column);
        $this->assertSame(4, $node->span->end->offset);
        $this->assertSame(1, $node->span->end->line);
        $this->assertSame(5, $node->span->end->column);
    }

    public function testMultilineObjectPositions(): void
    {
        $node = Maml::parseAst("{\n  a: 1\n}")->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $this->assertSame(0, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(1, $node->span->start->column);
        $prop = $node->properties[0];
        $this->assertSame(4, $prop->key->span->start->offset);
        $this->assertSame(2, $prop->key->span->start->line);
        $this->assertSame(3, $prop->key->span->start->column);
        $this->assertSame(7, $prop->value->span->start->offset);
        $this->assertSame(2, $prop->value->span->start->line);
        $this->assertSame(6, $prop->value->span->start->column);
    }

    public function testArrayElementPositions(): void
    {
        $node = Maml::parseAst('[1, 2]')->value;
        $this->assertInstanceOf(ArrayNode::class, $node);
        $this->assertSame(1, $node->elements[0]->value->span->start->offset);
        $this->assertSame(1, $node->elements[0]->value->span->start->line);
        $this->assertSame(2, $node->elements[0]->value->span->start->column);
        $this->assertSame(4, $node->elements[1]->value->span->start->offset);
        $this->assertSame(1, $node->elements[1]->value->span->start->line);
        $this->assertSame(5, $node->elements[1]->value->span->start->column);
    }

    public function testBooleanPositions(): void
    {
        $node = Maml::parseAst('true')->value;
        $this->assertSame(0, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(1, $node->span->start->column);
        $this->assertSame(4, $node->span->end->offset);
        $this->assertSame(1, $node->span->end->line);
        $this->assertSame(5, $node->span->end->column);
    }

    public function testNullPositions(): void
    {
        $node = Maml::parseAst('null')->value;
        $this->assertSame(0, $node->span->start->offset);
        $this->assertSame(1, $node->span->start->line);
        $this->assertSame(1, $node->span->start->column);
        $this->assertSame(4, $node->span->end->offset);
        $this->assertSame(1, $node->span->end->line);
        $this->assertSame(5, $node->span->end->column);
    }

    // ---- Key types ----

    public function testIdentifierKey(): void
    {
        $node = Maml::parseAst('{foo: 1}')->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $key = $node->properties[0]->key;
        $this->assertInstanceOf(IdentifierKey::class, $key);
        $this->assertSame('Identifier', $key->type);
        $this->assertSame('foo', $key->value);
    }

    public function testQuotedStringKey(): void
    {
        $node = Maml::parseAst('{"foo": 1}')->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $key = $node->properties[0]->key;
        $this->assertInstanceOf(StringNode::class, $key);
        $this->assertSame('String', $key->type);
        $this->assertSame('foo', $key->value);
        $this->assertSame('"foo"', $key->raw);
    }

    public function testIdentifierKeyWithDigits(): void
    {
        $node = Maml::parseAst('{123: "val"}')->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $key = $node->properties[0]->key;
        $this->assertInstanceOf(IdentifierKey::class, $key);
        $this->assertSame('123', $key->value);
    }

    // ---- Property spans ----

    public function testPropertySpanCoversKeyThroughValue(): void
    {
        $node = Maml::parseAst('{foo: 42}')->value;
        $this->assertInstanceOf(ObjectNode::class, $node);
        $prop = $node->properties[0];
        $this->assertSame($prop->key->span->start->offset, $prop->span->start->offset);
        $this->assertSame($prop->key->span->start->line, $prop->span->start->line);
        $this->assertSame($prop->key->span->start->column, $prop->span->start->column);
        $this->assertSame($prop->value->span->end->offset, $prop->span->end->offset);
        $this->assertSame($prop->value->span->end->line, $prop->span->end->line);
        $this->assertSame($prop->value->span->end->column, $prop->span->end->column);
    }

    // ---- Raw field ----

    public function testStringRawIncludesQuotes(): void
    {
        $node = Maml::parseAst('"hello"')->value;
        $this->assertInstanceOf(StringNode::class, $node);
        $this->assertSame('"hello"', $node->raw);
    }

    public function testStringWithEscapesRaw(): void
    {
        $node = Maml::parseAst('"a\\nb"')->value;
        $this->assertInstanceOf(StringNode::class, $node);
        $this->assertSame("a\nb", $node->value);
        $this->assertSame('"a\\nb"', $node->raw);
    }

    public function testRawStringRawIncludesTripleQuotes(): void
    {
        $node = Maml::parseAst('"""hello"""')->value;
        $this->assertInstanceOf(RawStringNode::class, $node);
        $this->assertSame('"""hello"""', $node->raw);
    }

    public function testNumberRawPreservesFormat(): void
    {
        $node = Maml::parseAst('1e6')->value;
        $this->assertInstanceOf(FloatNode::class, $node);
        $this->assertSame('1e6', $node->raw);
        $this->assertSame(1000000.0, $node->value);
    }

    public function testNegativeZeroRaw(): void
    {
        $node = Maml::parseAst('-0')->value;
        $this->assertInstanceOf(FloatNode::class, $node);
        $this->assertSame('-0', $node->raw);
        $this->assertSame(-0.0, $node->value);
    }

    // ---- Comment attachment: Document ----

    public function testDocumentLeadingComment(): void
    {
        $doc = Maml::parseAst("# header\n42");
        $this->assertCount(1, $doc->leadingComments);
        $this->assertSame(' header', $doc->leadingComments[0]->value);
    }

    public function testDocumentDanglingComment(): void
    {
        $doc = Maml::parseAst('42 # end');
        $this->assertCount(1, $doc->danglingComments);
        $this->assertSame(' end', $doc->danglingComments[0]->value);
    }

    public function testDocumentMultipleLeadingComments(): void
    {
        $doc = Maml::parseAst("# one\n# two\n42");
        $this->assertCount(2, $doc->leadingComments);
    }

    public function testDocumentNoComments(): void
    {
        $doc = Maml::parseAst('42');
        $this->assertSame([], $doc->leadingComments);
        $this->assertSame([], $doc->danglingComments);
    }

    // ---- Comment attachment: Object ----

    public function testObjectLeadingCommentOnProperty(): void
    {
        $doc = Maml::parseAst("{\n  # comment\n  a: 1\n}");
        $obj = $doc->value;
        $this->assertInstanceOf(ObjectNode::class, $obj);
        $this->assertCount(1, $obj->properties[0]->leadingComments);
        $this->assertSame(' comment', $obj->properties[0]->leadingComments[0]->value);
    }

    public function testObjectTrailingCommentOnProperty(): void
    {
        $doc = Maml::parseAst("{\n  a: 1 # inline\n}");
        $obj = $doc->value;
        $this->assertInstanceOf(ObjectNode::class, $obj);
        $this->assertNotNull($obj->properties[0]->trailingComment);
        $this->assertSame(' inline', $obj->properties[0]->trailingComment->value);
    }

    public function testObjectDanglingComment(): void
    {
        $doc = Maml::parseAst("{\n  a: 1\n  # end\n}");
        $obj = $doc->value;
        $this->assertInstanceOf(ObjectNode::class, $obj);
        $this->assertCount(1, $obj->danglingComments);
        $this->assertSame(' end', $obj->danglingComments[0]->value);
    }

    public function testObjectDanglingCommentInEmpty(): void
    {
        $doc = Maml::parseAst("{\n  # empty\n}");
        $obj = $doc->value;
        $this->assertInstanceOf(ObjectNode::class, $obj);
        $this->assertCount(1, $obj->danglingComments);
        $this->assertSame(' empty', $obj->danglingComments[0]->value);
    }

    public function testObjectLeadingAndTrailingOnSameProperty(): void
    {
        $doc = Maml::parseAst("{\n  # lead\n  a: 1 # trail\n}");
        $prop = $doc->value->properties[0];
        $this->assertCount(1, $prop->leadingComments);
        $this->assertSame(' lead', $prop->leadingComments[0]->value);
        $this->assertSame(' trail', $prop->trailingComment->value);
    }

    public function testObjectCommentsBetweenProperties(): void
    {
        $doc = Maml::parseAst("{\n  a: 1 # after a\n  # before b\n  b: 2\n}");
        $props = $doc->value->properties;
        $this->assertSame(' after a', $props[0]->trailingComment->value);
        $this->assertSame(' before b', $props[1]->leadingComments[0]->value);
    }

    // ---- Comment attachment: Array ----

    public function testArrayTrailingCommentOnElement(): void
    {
        $doc = Maml::parseAst("[\n  1 # one\n  2 # two\n]");
        $arr = $doc->value;
        $this->assertInstanceOf(ArrayNode::class, $arr);
        $this->assertSame(' one', $arr->elements[0]->trailingComment->value);
        $this->assertSame(' two', $arr->elements[1]->trailingComment->value);
        $this->assertCount(0, $arr->danglingComments);
    }

    public function testArrayLeadingCommentOnElement(): void
    {
        $doc = Maml::parseAst("[\n  # first\n  1\n  2\n]");
        $arr = $doc->value;
        $this->assertInstanceOf(ArrayNode::class, $arr);
        $this->assertCount(1, $arr->elements[0]->leadingComments);
        $this->assertSame(' first', $arr->elements[0]->leadingComments[0]->value);
        $this->assertCount(0, $arr->elements[1]->leadingComments);
    }

    public function testArrayDanglingComment(): void
    {
        $doc = Maml::parseAst("[\n  1\n  # end\n]");
        $arr = $doc->value;
        $this->assertInstanceOf(ArrayNode::class, $arr);
        $this->assertCount(1, $arr->danglingComments);
        $this->assertSame(' end', $arr->danglingComments[0]->value);
    }

    public function testArrayDanglingCommentInEmpty(): void
    {
        $doc = Maml::parseAst("[\n  # empty\n]");
        $arr = $doc->value;
        $this->assertInstanceOf(ArrayNode::class, $arr);
        $this->assertCount(1, $arr->danglingComments);
        $this->assertSame(' empty', $arr->danglingComments[0]->value);
    }

    public function testArrayCommentInsideNestedObject(): void
    {
        $doc = Maml::parseAst("[\n  {\n    # nested\n    a: 1\n  }\n]");
        $arr = $doc->value;
        $this->assertInstanceOf(ArrayNode::class, $arr);
        $this->assertCount(0, $arr->danglingComments);
        $obj = $arr->elements[0]->value;
        $this->assertInstanceOf(ObjectNode::class, $obj);
        $this->assertCount(1, $obj->properties[0]->leadingComments);
        $this->assertSame(' nested', $obj->properties[0]->leadingComments[0]->value);
    }

    public function testArrayLeadingAndTrailingOnSameElement(): void
    {
        $doc = Maml::parseAst("[\n  # lead\n  1 # trail\n]");
        $el = $doc->value->elements[0];
        $this->assertCount(1, $el->leadingComments);
        $this->assertSame(' lead', $el->leadingComments[0]->value);
        $this->assertSame(' trail', $el->trailingComment->value);
    }

    public function testArrayCommentsBetweenElements(): void
    {
        $doc = Maml::parseAst("[\n  1 # after 1\n  # before 2\n  2\n]");
        $els = $doc->value->elements;
        $this->assertSame(' after 1', $els[0]->trailingComment->value);
        $this->assertSame(' before 2', $els[1]->leadingComments[0]->value);
    }

    // ---- Blank lines: Object ----

    public function testObjectBlankLineBetweenProperties(): void
    {
        $doc = Maml::parseAst("{\n  a: 1\n\n  b: 2\n}");
        $props = $doc->value->properties;
        $this->assertFalse($props[0]->emptyLineBefore);
        $this->assertTrue($props[1]->emptyLineBefore);
    }

    public function testObjectNoBlankLineBetweenProperties(): void
    {
        $doc = Maml::parseAst("{\n  a: 1\n  b: 2\n}");
        $props = $doc->value->properties;
        $this->assertFalse($props[0]->emptyLineBefore);
        $this->assertFalse($props[1]->emptyLineBefore);
    }

    public function testObjectBlankLineBeforeCommentGroup(): void
    {
        $doc = Maml::parseAst("{\n  a: 1\n\n  # section\n  b: 2\n}");
        $props = $doc->value->properties;
        $this->assertTrue($props[1]->emptyLineBefore);
    }

    public function testObjectNestedBlankLines(): void
    {
        $doc = Maml::parseAst("{\n  x: {\n    a: 1\n\n    b: 2\n  }\n}");
        $inner = $doc->value->properties[0]->value;
        $this->assertInstanceOf(ObjectNode::class, $inner);
        $this->assertFalse($inner->properties[0]->emptyLineBefore);
        $this->assertTrue($inner->properties[1]->emptyLineBefore);
    }

    // ---- Blank lines: Array ----

    public function testArrayBlankLineBetweenElements(): void
    {
        $doc = Maml::parseAst("[\n  1\n\n  2\n]");
        $els = $doc->value->elements;
        $this->assertFalse($els[0]->emptyLineBefore);
        $this->assertTrue($els[1]->emptyLineBefore);
    }

    public function testArrayNoBlankLineBetweenElements(): void
    {
        $doc = Maml::parseAst("[\n  1\n  2\n]");
        $els = $doc->value->elements;
        $this->assertFalse($els[0]->emptyLineBefore);
        $this->assertFalse($els[1]->emptyLineBefore);
    }

    public function testArrayNestedBlankLines(): void
    {
        $doc = Maml::parseAst("[\n  [\n    1\n\n    2\n  ]\n]");
        $inner = $doc->value->elements[0]->value;
        $this->assertInstanceOf(ArrayNode::class, $inner);
        $this->assertFalse($inner->elements[0]->emptyLineBefore);
        $this->assertTrue($inner->elements[1]->emptyLineBefore);
    }

    public function testArrayBlankLineBeforeCommentGroup(): void
    {
        $doc = Maml::parseAst("[\n  1\n\n  # section\n  2\n]");
        $els = $doc->value->elements;
        $this->assertTrue($els[1]->emptyLineBefore);
    }

    // ---- Error cases ----

    public function testErrorOnInvalidInput(): void
    {
        $this->expectException(ParseException::class);
        Maml::parseAst('???');
    }

    public function testErrorOnDuplicateKey(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Duplicate key');
        Maml::parseAst('{a: 1, a: 2}');
    }

    public function testErrorOnIntegerOverflow(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Integer overflow');
        Maml::parseAst('99999999999999999999');
    }

    public function testErrorOnSurrogateCodePoint(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('out of range');
        Maml::parseAst('"\u{D800}"');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function errorProvider(): array
    {
        return self::loadTestCases('error.test.txt');
    }

    #[DataProvider('errorProvider')]
    public function testErrorCases(string $name, string $input, string $expected): void
    {
        $this->expectException(ParseException::class);
        Maml::parseAst($input);
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
