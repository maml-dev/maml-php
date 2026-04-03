<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Ast\ObjectNode;
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
        $reparsed = Maml::parse($stringified);
        $this->assertSame($parsed, $reparsed);
    }

    public function testErrorSnippetPointsAtPosition(): void
    {
        $source = "{\n  name: \"test\"\n  timeout: -1\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $node = $doc->value->properties[1]->value;

        $result = Maml::errorSnippet($source, $node->span->start, 'Invalid value');
        $this->assertSame(
            "Invalid value on line 3.\n\n      timeout: -1\n    ...........^\n",
            $result,
        );
    }

    public function testErrorSnippetAtStartOfSource(): void
    {
        $source = 'null';
        $doc = Maml::parseAst($source);

        $result = Maml::errorSnippet($source, $doc->value->span->start, 'Expected object');
        $this->assertSame(
            "Expected object on line 1.\n\n    null\n    ^\n",
            $result,
        );
    }

    public function testErrorSnippetOnNestedNode(): void
    {
        $source = "{\n  items: [\n    {name: \"x\", count: 0}\n  ]\n}";
        $doc = Maml::parseAst($source);
        $this->assertInstanceOf(ObjectNode::class, $doc->value);
        $items = $doc->value->properties[0]->value;
        $this->assertInstanceOf(\Maml\Ast\ArrayNode::class, $items);
        $inner = $items->elements[0]->value;
        $this->assertInstanceOf(ObjectNode::class, $inner);
        $countNode = $inner->properties[1]->value;

        $result = Maml::errorSnippet($source, $countNode->span->start, 'Count must be positive');
        $this->assertSame(
            "Count must be positive on line 3.\n\n        {name: \"x\", count: 0}\n    .......................^\n",
            $result,
        );
    }
}
