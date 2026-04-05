<?php

declare(strict_types=1);

namespace Maml\Tests;

use Maml\Maml;
use Maml\Schema\S;
use Maml\Schema\ValidationError;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    // ---- Primitive types ----

    public function testStringMatchesStringNode(): void
    {
        $this->assertValid('"hello"', S::string());
    }

    public function testStringMatchesRawStringNode(): void
    {
        $this->assertValid('"""raw"""', S::string());
    }

    public function testStringRejectsInteger(): void
    {
        $errors = $this->validate('42', S::string());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$', $errors[0]->path);
    }

    public function testIntegerMatchesIntegerNode(): void
    {
        $this->assertValid('42', S::integer());
    }

    public function testIntegerRejectsFloat(): void
    {
        $errors = $this->validate('3.14', S::integer());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got float', $errors[0]->message);
    }

    public function testFloatMatchesFloatNode(): void
    {
        $this->assertValid('3.14', S::float());
    }

    public function testFloatRejectsInteger(): void
    {
        $errors = $this->validate('42', S::float());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected float, got integer', $errors[0]->message);
    }

    public function testNumberMatchesInteger(): void
    {
        $this->assertValid('42', S::number());
    }

    public function testNumberMatchesFloat(): void
    {
        $this->assertValid('3.14', S::number());
    }

    public function testNumberRejectsString(): void
    {
        $errors = $this->validate('"x"', S::number());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected number, got string', $errors[0]->message);
    }

    public function testBooleanMatchesBooleanNode(): void
    {
        $this->assertValid('true', S::boolean());
        $this->assertValid('false', S::boolean());
    }

    public function testBooleanRejectsString(): void
    {
        $errors = $this->validate('"yes"', S::boolean());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected boolean, got string', $errors[0]->message);
    }

    public function testNullMatchesNullNode(): void
    {
        $this->assertValid('null', S::null());
    }

    public function testNullRejectsInteger(): void
    {
        $errors = $this->validate('0', S::null());
        $this->assertCount(1, $errors);
        $this->assertSame('Expected null, got integer', $errors[0]->message);
    }

    // ---- Literal values ----

    public function testLiteralStringMatch(): void
    {
        $this->assertValid('"fast"', S::literal('fast'));
    }

    public function testLiteralStringMismatchValue(): void
    {
        $errors = $this->validate('"slow"', S::literal('fast'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected "fast", got string "slow"', $errors[0]->message);
    }

    public function testLiteralStringMismatchType(): void
    {
        $errors = $this->validate('42', S::literal('fast'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected "fast", got integer 42', $errors[0]->message);
    }

    public function testLiteralIntegerMatch(): void
    {
        $this->assertValid('42', S::literal(42));
    }

    public function testLiteralIntegerMismatchValue(): void
    {
        $errors = $this->validate('7', S::literal(42));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected 42, got integer 7', $errors[0]->message);
    }

    public function testLiteralFloatMatch(): void
    {
        $this->assertValid('3.14', S::literal(3.14));
    }

    public function testLiteralFloatMismatch(): void
    {
        $errors = $this->validate('2.71', S::literal(3.14));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected 3.14, got float 2.71', $errors[0]->message);
    }

    public function testLiteralBoolMatch(): void
    {
        $this->assertValid('true', S::literal(true));
    }

    public function testLiteralBoolMismatchValue(): void
    {
        $errors = $this->validate('false', S::literal(true));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected true, got false', $errors[0]->message);
    }

    public function testLiteralBoolMismatchType(): void
    {
        $errors = $this->validate('1', S::literal(true));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected true, got integer 1', $errors[0]->message);
    }

    public function testLiteralNullMatch(): void
    {
        $this->assertValid('null', S::literal(null));
    }

    public function testLiteralNullMismatch(): void
    {
        $errors = $this->validate('0', S::literal(null));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected null, got integer', $errors[0]->message);
    }

    // ---- Any ----

    public function testAnyMatchesEverything(): void
    {
        $this->assertValid('"x"', S::any());
        $this->assertValid('42', S::any());
        $this->assertValid('true', S::any());
        $this->assertValid('null', S::any());
        $this->assertValid('{}', S::any());
        $this->assertValid('[]', S::any());
    }

    // ---- Object ----

    public function testObjectValid(): void
    {
        $this->assertValid(
            '{host: "localhost", port: 5432}',
            S::object(['host' => S::string(), 'port' => S::integer()]),
        );
    }

    public function testObjectMissingRequiredKey(): void
    {
        $errors = $this->validate(
            '{host: "localhost"}',
            S::object(['host' => S::string(), 'port' => S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Missing required property "port"', $errors[0]->message);
        $this->assertSame('$.port', $errors[0]->path);
    }

    public function testObjectUnknownKey(): void
    {
        $errors = $this->validate(
            '{host: "localhost", port: 5432, extra: true}',
            S::object(['host' => S::string(), 'port' => S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Unknown property "extra"', $errors[0]->message);
        $this->assertSame('$.extra', $errors[0]->path);
    }

    public function testObjectWrongValueType(): void
    {
        $errors = $this->validate(
            '{host: "localhost", port: "not a number"}',
            S::object(['host' => S::string(), 'port' => S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got string', $errors[0]->message);
        $this->assertSame('$.port', $errors[0]->path);
    }

    public function testObjectNotAnObject(): void
    {
        $errors = $this->validate(
            '[1, 2, 3]',
            S::object(['x' => S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected object{x}, got array', $errors[0]->message);
    }

    public function testObjectOptionalKeyAbsent(): void
    {
        $this->assertValid(
            '{host: "localhost"}',
            S::object(['host' => S::string(), 'port' => S::optional(S::integer())]),
        );
    }

    public function testObjectOptionalKeyPresent(): void
    {
        $this->assertValid(
            '{host: "localhost", port: 5432}',
            S::object(['host' => S::string(), 'port' => S::optional(S::integer())]),
        );
    }

    public function testObjectOptionalKeyWrongType(): void
    {
        $errors = $this->validate(
            '{host: "localhost", port: "bad"}',
            S::object(['host' => S::string(), 'port' => S::optional(S::integer())]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got string', $errors[0]->message);
        $this->assertSame('$.port', $errors[0]->path);
    }

    public function testObjectEmpty(): void
    {
        $this->assertValid('{}', S::object([]));
    }

    public function testObjectMultipleErrors(): void
    {
        $errors = $this->validate(
            '{extra: true}',
            S::object(['host' => S::string(), 'port' => S::integer()]),
        );
        $this->assertCount(3, $errors);
        $messages = \array_map(fn(ValidationError $e) => $e->message, $errors);
        $this->assertContains('Unknown property "extra"', $messages);
        $this->assertContains('Missing required property "host"', $messages);
        $this->assertContains('Missing required property "port"', $messages);
    }

    // ---- Object with additionalProperties ----

    public function testObjectAdditionalPropertiesAllowed(): void
    {
        $this->assertValid(
            '{host: "localhost", port: 5432, extra: true}',
            S::object(['host' => S::string(), 'port' => S::integer()], S::any()),
        );
    }

    public function testObjectAdditionalPropertiesTyped(): void
    {
        $this->assertValid(
            '{host: "localhost", FOO: "bar", BAZ: "qux"}',
            S::object(['host' => S::string()], S::string()),
        );
    }

    public function testObjectAdditionalPropertiesTypedRejectsWrongType(): void
    {
        $errors = $this->validate(
            '{host: "localhost", count: 42}',
            S::object(['host' => S::string()], S::string()),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$.count', $errors[0]->path);
    }

    public function testObjectAdditionalPropertiesStillValidatesKnown(): void
    {
        $errors = $this->validate(
            '{host: 42, extra: "ok"}',
            S::object(['host' => S::string()], S::any()),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$.host', $errors[0]->path);
    }

    public function testObjectAdditionalPropertiesHostsExample(): void
    {
        $schema = S::map(S::union(
            S::object(['local' => S::optional(S::boolean())], S::any()),
            S::null(),
        ));
        $this->assertValid('{prod: {local: false, deploy_path: "/var/www"}, staging: null}', $schema);
    }

    public function testOrderedObjectAdditionalProperties(): void
    {
        $this->assertValid(
            '{a: 1, b: 2, extra: "ok"}',
            S::orderedObject(['a' => S::integer(), 'b' => S::integer()], S::any()),
        );
    }

    // ---- Ordered object ----

    public function testOrderedObjectValidOrder(): void
    {
        $this->assertValid(
            '{a: 1, b: 2, c: 3}',
            S::orderedObject(['a' => S::integer(), 'b' => S::integer(), 'c' => S::integer()]),
        );
    }

    public function testOrderedObjectWrongOrder(): void
    {
        $errors = $this->validate(
            '{b: 2, a: 1, c: 3}',
            S::orderedObject(['a' => S::integer(), 'b' => S::integer(), 'c' => S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('not in the expected order', $errors[0]->message);
        $this->assertStringContainsString('a, b, c', $errors[0]->message);
    }

    public function testOrderedObjectOptionalAbsentPreservesOrder(): void
    {
        $this->assertValid(
            '{a: 1, c: 3}',
            S::orderedObject([
                'a' => S::integer(),
                'b' => S::optional(S::integer()),
                'c' => S::integer(),
            ]),
        );
    }

    public function testOrderedObjectOptionalAbsentWrongOrder(): void
    {
        $errors = $this->validate(
            '{c: 3, a: 1}',
            S::orderedObject([
                'a' => S::integer(),
                'b' => S::optional(S::integer()),
                'c' => S::integer(),
            ]),
        );
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('not in the expected order', $errors[0]->message);
    }

    // ---- Map ----

    public function testMapValid(): void
    {
        $this->assertValid(
            '{HOME: "/home/user", PATH: "/usr/bin"}',
            S::map(S::string()),
        );
    }

    public function testMapEmpty(): void
    {
        $this->assertValid('{}', S::map(S::string()));
    }

    public function testMapWrongValueType(): void
    {
        $errors = $this->validate(
            '{HOST: "localhost", PORT: 5432}',
            S::map(S::string()),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$.PORT', $errors[0]->path);
    }

    public function testMapNotAnObject(): void
    {
        $errors = $this->validate('[1, 2]', S::map(S::integer()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected map<integer>, got array', $errors[0]->message);
    }

    public function testMapInsideObject(): void
    {
        $schema = S::object([
            'run' => S::string(),
            'env' => S::optional(S::map(S::string())),
        ]);
        $this->assertValid('{run: "echo hi", env: {FOO: "bar", BAZ: "qux"}}', $schema);
        $this->assertValid('{run: "echo hi"}', $schema);

        $errors = $this->validate('{run: "echo hi", env: {FOO: 42}}', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('$.env.FOO', $errors[0]->path);
    }

    // ---- ArrayOf ----

    public function testArrayOfValid(): void
    {
        $this->assertValid('[1, 2, 3]', S::arrayOf(S::integer()));
    }

    public function testArrayOfEmpty(): void
    {
        $this->assertValid('[]', S::arrayOf(S::string()));
    }

    public function testArrayOfMixedTypes(): void
    {
        $errors = $this->validate('[1, "two", 3]', S::arrayOf(S::integer()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got string', $errors[0]->message);
        $this->assertSame('$[1]', $errors[0]->path);
    }

    public function testArrayOfNotAnArray(): void
    {
        $errors = $this->validate('"x"', S::arrayOf(S::integer()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer[], got string', $errors[0]->message);
    }

    // ---- Tuple ----

    public function testTupleValid(): void
    {
        $this->assertValid(
            '[1, "hello", true]',
            S::tuple([S::integer(), S::string(), S::boolean()]),
        );
    }

    public function testTupleWrongLength(): void
    {
        $errors = $this->validate(
            '[1, 2]',
            S::tuple([S::integer(), S::integer(), S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected array of length 3, got 2', $errors[0]->message);
    }

    public function testTupleWrongElementType(): void
    {
        $errors = $this->validate(
            '[1, 2, "three"]',
            S::tuple([S::integer(), S::integer(), S::integer()]),
        );
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got string', $errors[0]->message);
        $this->assertSame('$[2]', $errors[0]->path);
    }

    public function testTupleNotAnArray(): void
    {
        $errors = $this->validate('"x"', S::tuple([S::integer()]));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected [integer], got string', $errors[0]->message);
    }

    // ---- Union ----

    public function testUnionMatchesFirstBranch(): void
    {
        $this->assertValid('"hello"', S::union(S::string(), S::integer()));
    }

    public function testUnionMatchesSecondBranch(): void
    {
        $this->assertValid('42', S::union(S::string(), S::integer()));
    }

    public function testUnionMatchesNone(): void
    {
        $errors = $this->validate('true', S::union(S::string(), S::integer()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string | integer, got boolean', $errors[0]->message);
    }

    // ---- Enum ----

    public function testEnumValid(): void
    {
        $this->assertValid('"fast"', S::enum('fast', 'safe', 'auto'));
    }

    public function testEnumInvalid(): void
    {
        $errors = $this->validate('"slow"', S::enum('fast', 'safe', 'auto'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected "fast" | "safe" | "auto", got string', $errors[0]->message);
    }

    // ---- Nested structures ----

    public function testNestedObjectPaths(): void
    {
        $schema = S::object([
            'server' => S::object([
                'host' => S::string(),
                'port' => S::integer(),
            ]),
        ]);
        $errors = $this->validate('{server: {host: 123, port: "bad"}}', $schema);
        $this->assertCount(2, $errors);
        $this->assertSame('$.server.host', $errors[0]->path);
        $this->assertSame('$.server.port', $errors[1]->path);
    }

    public function testNestedArrayPaths(): void
    {
        $schema = S::arrayOf(S::object(['name' => S::string()]));
        $errors = $this->validate('[{name: "ok"}, {name: 42}]', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('$[1].name', $errors[0]->path);
    }

    public function testDeeplyNestedPath(): void
    {
        $schema = S::object([
            'a' => S::arrayOf(S::object([
                'b' => S::string(),
            ])),
        ]);
        $errors = $this->validate('{a: [{b: "ok"}, {b: 42}]}', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('$.a[1].b', $errors[0]->path);
    }

    // ---- Position tracking ----

    public function testErrorPositionPointsAtNode(): void
    {
        $errors = $this->validate("{\n  port: \"bad\"\n}", S::object(['port' => S::integer()]));
        $this->assertCount(1, $errors);
        $this->assertNotNull($errors[0]->span);
        $this->assertSame(2, $errors[0]->span->start->line);
    }

    public function testMissingKeySpanPointsAtObject(): void
    {
        $errors = $this->validate('{}', S::object(['name' => S::string()]));
        $this->assertCount(1, $errors);
        $this->assertNotNull($errors[0]->span);
        $this->assertSame(1, $errors[0]->span->start->line);
    }

    public function testUnknownKeySpanPointsAtKey(): void
    {
        $errors = $this->validate('{bad: 1}', S::object([]));
        $this->assertCount(1, $errors);
        $this->assertNotNull($errors[0]->span);
        $this->assertSame(2, $errors[0]->span->start->column);
    }

    // ---- Complex schemas ----

    public function testRealWorldConfigSchema(): void
    {
        $schema = S::object([
            'name' => S::string(),
            'version' => S::integer(),
            'debug' => S::optional(S::boolean()),
            'mode' => S::enum('fast', 'slow', 'auto'),
            'tags' => S::arrayOf(S::string()),
            'server' => S::object([
                'host' => S::string(),
                'port' => S::integer(),
            ]),
        ]);

        $this->assertValid('{
            name: "myapp"
            version: 1
            mode: "fast"
            tags: ["web", "api"]
            server: {
                host: "localhost"
                port: 8080
            }
        }', $schema);
    }

    public function testUnionOfObjectShapes(): void
    {
        $schema = S::union(
            S::object(['type' => S::literal('file'), 'path' => S::string()]),
            S::object(['type' => S::literal('url'), 'href' => S::string()]),
        );
        $this->assertValid('{type: "file", path: "/tmp/x"}', $schema);
        $this->assertValid('{type: "url", href: "https://x.com"}', $schema);

        $errors = $this->validate('{type: "other"}', $schema);
        $this->assertCount(1, $errors);
    }

    // ---- Optional outside object context ----

    public function testOptionalAtRootUnwraps(): void
    {
        $this->assertValid('"hello"', S::optional(S::string()));
    }

    public function testOptionalAtRootReportsInner(): void
    {
        $errors = $this->validate('42', S::optional(S::string()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
    }

    // ---- Literal edge cases for describeNodeValue ----

    public function testLiteralMismatchOnNull(): void
    {
        $errors = $this->validate('null', S::literal(42));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected 42, got null', $errors[0]->message);
    }

    public function testLiteralMismatchOnObject(): void
    {
        $errors = $this->validate('{}', S::literal('x'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected "x", got object', $errors[0]->message);
    }

    public function testLiteralMismatchOnArray(): void
    {
        $errors = $this->validate('[]', S::literal('x'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected "x", got array', $errors[0]->message);
    }

    // ---- Schema describe() methods ----

    public function testSchemaDescribe(): void
    {
        $this->assertSame('string', S::string()->describe());
        $this->assertSame('integer', S::integer()->describe());
        $this->assertSame('float', S::float()->describe());
        $this->assertSame('number', S::number()->describe());
        $this->assertSame('boolean', S::boolean()->describe());
        $this->assertSame('null', S::null()->describe());
        $this->assertSame('any', S::any()->describe());
        $this->assertSame('"fast"', S::literal('fast')->describe());
        $this->assertSame('42', S::literal(42)->describe());
        $this->assertSame('null', S::literal(null)->describe());
        $this->assertSame('true', S::literal(true)->describe());
        $this->assertSame('false', S::literal(false)->describe());
        $this->assertSame('3.14', S::literal(3.14)->describe());
        $this->assertSame('string?', S::optional(S::string())->describe());
        $this->assertSame('string[]', S::arrayOf(S::string())->describe());
        $this->assertSame('[integer, string]', S::tuple([S::integer(), S::string()])->describe());
        $this->assertSame('string | integer', S::union(S::string(), S::integer())->describe());
        $this->assertSame('object{a, b}', S::object(['a' => S::string(), 'b' => S::integer()])->describe());
        $this->assertSame('ordered object{a, b}', S::orderedObject(['a' => S::string(), 'b' => S::integer()])->describe());
        $this->assertSame('map<string>', S::map(S::string())->describe());
    }

    // ---- Helpers ----

    /**
     * @return ValidationError[]
     */
    private function validate(string $source, \Maml\Schema\SchemaType $schema): array
    {
        $doc = Maml::parseAst($source);
        return Maml::validate($doc, $schema);
    }

    private function assertValid(string $source, \Maml\Schema\SchemaType $schema): void
    {
        $errors = $this->validate($source, $schema);
        $messages = \array_map(fn(ValidationError $e) => $e->path . ': ' . $e->message, $errors);
        $this->assertSame([], $messages);
    }
}
