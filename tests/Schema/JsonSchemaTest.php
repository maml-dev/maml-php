<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Maml;
use Maml\Schema\S;
use PHPUnit\Framework\TestCase;

final class JsonSchemaTest extends TestCase
{
    // ---- Primitives ----

    public function testString(): void
    {
        $this->assertJsonSchema(S::string(), [
            'type' => 'string',
        ]);
    }

    public function testStringWithPattern(): void
    {
        $this->assertJsonSchema(S::string(pattern: '/^\d{4}-\d{2}-\d{2}$/'), [
            'type' => 'string',
            'pattern' => '^\d{4}-\d{2}-\d{2}$',
        ]);
    }

    public function testInteger(): void
    {
        $this->assertJsonSchema(S::integer(), [
            'type' => 'integer',
        ]);
    }

    public function testIntegerWithRange(): void
    {
        $this->assertJsonSchema(S::integer(min: 0, max: 65535), [
            'type' => 'integer',
            'minimum' => 0,
            'maximum' => 65535,
        ]);
    }

    public function testIntegerMinOnly(): void
    {
        $this->assertJsonSchema(S::integer(min: 1), [
            'type' => 'integer',
            'minimum' => 1,
        ]);
    }

    public function testFloat(): void
    {
        $this->assertJsonSchema(S::float(), [
            'type' => 'number',
        ]);
    }

    public function testFloatWithRange(): void
    {
        $this->assertJsonSchema(S::float(min: 0.0, max: 1.0), [
            'type' => 'number',
            'minimum' => 0.0,
            'maximum' => 1.0,
        ]);
    }

    public function testNumber(): void
    {
        $this->assertJsonSchema(S::number(), [
            'type' => 'number',
        ]);
    }

    public function testNumberWithRange(): void
    {
        $this->assertJsonSchema(S::number(min: 0, max: 100), [
            'type' => 'number',
            'minimum' => 0,
            'maximum' => 100,
        ]);
    }

    public function testBoolean(): void
    {
        $this->assertJsonSchema(S::boolean(), [
            'type' => 'boolean',
        ]);
    }

    public function testNull(): void
    {
        $this->assertJsonSchema(S::null(), [
            'type' => 'null',
        ]);
    }

    public function testAny(): void
    {
        $this->assertJsonSchema(S::any(), []);
    }

    // ---- Literals ----

    public function testLiteralString(): void
    {
        $this->assertJsonSchema(S::literal('fast'), [
            'const' => 'fast',
        ]);
    }

    public function testLiteralInteger(): void
    {
        $this->assertJsonSchema(S::literal(42), [
            'const' => 42,
        ]);
    }

    public function testLiteralBool(): void
    {
        $this->assertJsonSchema(S::literal(true), [
            'const' => true,
        ]);
    }

    public function testLiteralNull(): void
    {
        $this->assertJsonSchema(S::literal(null), [
            'const' => null,
        ]);
    }

    // ---- Object ----

    public function testObjectStrict(): void
    {
        $this->assertJsonSchema(
            S::object([
                'host' => S::string(),
                'port' => S::integer(),
            ]),
            [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => 'string'],
                    'port' => ['type' => 'integer'],
                ],
                'required' => ['host', 'port'],
                'additionalProperties' => false,
            ],
        );
    }

    public function testObjectWithOptional(): void
    {
        $this->assertJsonSchema(
            S::object([
                'host' => S::string(),
                'port' => S::optional(S::integer()),
            ]),
            [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => 'string'],
                    'port' => ['type' => 'integer'],
                ],
                'required' => ['host'],
                'additionalProperties' => false,
            ],
        );
    }

    public function testObjectAllOptional(): void
    {
        $this->assertJsonSchema(
            S::object([
                'debug' => S::optional(S::boolean()),
            ]),
            [
                'type' => 'object',
                'properties' => [
                    'debug' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
        );
    }

    public function testObjectAdditionalPropertiesAny(): void
    {
        $this->assertJsonSchema(
            S::object(['host' => S::string()], S::any()),
            [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => 'string'],
                ],
                'required' => ['host'],
            ],
        );
    }

    public function testObjectAdditionalPropertiesTyped(): void
    {
        $this->assertJsonSchema(
            S::object(['host' => S::string()], S::string()),
            [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => 'string'],
                ],
                'required' => ['host'],
                'additionalProperties' => ['type' => 'string'],
            ],
        );
    }

    public function testObjectEmpty(): void
    {
        $this->assertJsonSchema(
            S::object([]),
            [
                'type' => 'object',
                'additionalProperties' => false,
            ],
        );
    }

    public function testOrderedObjectSameAsObject(): void
    {
        // JSON Schema doesn't have property ordering, so same output
        $this->assertJsonSchema(
            S::orderedObject(['a' => S::integer(), 'b' => S::string()]),
            [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'integer'],
                    'b' => ['type' => 'string'],
                ],
                'required' => ['a', 'b'],
                'additionalProperties' => false,
            ],
        );
    }

    // ---- Map ----

    public function testMap(): void
    {
        $this->assertJsonSchema(S::map(S::string()), [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ]);
    }

    public function testMapAny(): void
    {
        $this->assertJsonSchema(S::map(S::any()), [
            'type' => 'object',
            'additionalProperties' => true,
        ]);
    }

    // ---- Array ----

    public function testArrayOf(): void
    {
        $this->assertJsonSchema(S::arrayOf(S::string()), [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ]);
    }

    public function testArrayOfWithMinMax(): void
    {
        $this->assertJsonSchema(S::arrayOf(S::string(), minItems: 1, maxItems: 10), [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'minItems' => 1,
            'maxItems' => 10,
        ]);
    }

    public function testArrayOfMinOnly(): void
    {
        $this->assertJsonSchema(S::arrayOf(S::integer(), minItems: 1), [
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'minItems' => 1,
        ]);
    }

    public function testTuple(): void
    {
        $this->assertJsonSchema(
            S::tuple([S::integer(), S::string(), S::boolean()]),
            [
                'type' => 'array',
                'items' => [
                    ['type' => 'integer'],
                    ['type' => 'string'],
                    ['type' => 'boolean'],
                ],
                'additionalItems' => false,
                'minItems' => 3,
                'maxItems' => 3,
            ],
        );
    }

    // ---- Union / Enum ----

    public function testUnion(): void
    {
        $this->assertJsonSchema(S::union(S::string(), S::integer()), [
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ]);
    }

    public function testEnum(): void
    {
        $this->assertJsonSchema(S::enum('fast', 'safe', 'auto'), [
            'enum' => ['fast', 'safe', 'auto'],
        ]);
    }

    public function testUnionMixedNotEnum(): void
    {
        // Union of non-literals still uses oneOf
        $this->assertJsonSchema(S::union(S::string(), S::null()), [
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'null'],
            ],
        ]);
    }

    // ---- Nested ----

    public function testNestedObjectInArray(): void
    {
        $this->assertJsonSchema(
            S::arrayOf(S::object(['name' => S::string()])),
            [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => false,
                ],
            ],
        );
    }

    // ---- Full schema ----

    public function testHasSchemaKey(): void
    {
        $result = Maml::jsonSchema(S::string());
        $this->assertSame('http://json-schema.org/draft-07/schema#', $result['$schema']);
        $this->assertSame('string', $result['type']);
    }

    public function testRealWorldSchema(): void
    {
        $schema = S::object([
            'name' => S::string(),
            'version' => S::integer(min: 1),
            'mode' => S::enum('fast', 'safe'),
            'tags' => S::arrayOf(S::string(), minItems: 1),
            'server' => S::object([
                'host' => S::string(),
                'port' => S::integer(min: 1, max: 65535),
            ]),
            'env' => S::optional(S::map(S::string())),
        ]);

        $result = Maml::jsonSchema($schema);
        $this->assertSame('http://json-schema.org/draft-07/schema#', $result['$schema']);
        $this->assertSame('object', $result['type']);
        $this->assertSame(['name', 'version', 'mode', 'tags', 'server'], $result['required']);
        $this->assertSame(false, $result['additionalProperties']);

        $this->assertIsArray($result['properties']);
        /** @var array<string, array<string, mixed>> $props */
        $props = $result['properties'];
        $this->assertSame(['type' => 'string'], $props['name']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1], $props['version']);
        $this->assertSame(1, $props['tags']['minItems']);

        $this->assertIsArray($props['server']['properties']);
        /** @var array<string, array<string, mixed>> $serverProps */
        $serverProps = $props['server']['properties'];
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 65535], $serverProps['port']);
        $this->assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'string']], $props['env']);
    }

    public function testOutputIsValidJson(): void
    {
        $schema = S::object([
            'name' => S::string(),
            'count' => S::integer(min: 0),
        ]);
        $json = \json_encode(Maml::jsonSchema($schema), \JSON_PRETTY_PRINT);
        $this->assertIsString($json);
        $decoded = \json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('http://json-schema.org/draft-07/schema#', $decoded['$schema']);
    }

    // ---- Pattern delimiter stripping ----

    public function testPatternStripsSlashDelimiters(): void
    {
        $result = Maml::jsonSchema(S::string(pattern: '/^[a-z]+$/'));
        $this->assertSame('^[a-z]+$', $result['pattern']);
    }

    public function testPatternStripsHashDelimiters(): void
    {
        $result = Maml::jsonSchema(S::string(pattern: '#^\d+$#'));
        $this->assertSame('^\d+$', $result['pattern']);
    }

    public function testPatternStripsDelimitersWithFlags(): void
    {
        $result = Maml::jsonSchema(S::string(pattern: '/pattern/i'));
        $this->assertSame('pattern', $result['pattern']);
    }

    public function testPatternPlainStringNoDelimiters(): void
    {
        // Edge case: a single character is not a valid delimited pattern
        $result = Maml::jsonSchema(S::string(pattern: 'x'));
        $this->assertSame('x', $result['pattern']);
    }

    // ---- Optional at top level ----

    public function testOptionalUnwrapsInConvert(): void
    {
        // Optional inside a union — convert() receives OptionalType directly
        $schema = S::union(S::optional(S::string()), S::integer());
        $result = Maml::jsonSchema($schema);
        $this->assertSame([
            ['type' => 'string'],
            ['type' => 'integer'],
        ], $result['oneOf']);
    }

    // ---- Helper ----

    /**
     * @param array<string, mixed> $expected
     */
    private function assertJsonSchema(\Maml\Schema\SchemaType $schema, array $expected): void
    {
        $result = Maml::jsonSchema($schema);
        unset($result['$schema']);
        $this->assertSame($expected, $result);
    }
}
