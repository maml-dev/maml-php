<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Schema\S;
use Maml\Schema\ValidationError;
use PHPUnit\Framework\TestCase;

final class ObjectTest extends TestCase
{
    use ValidatorTestTrait;

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

    // ---- additionalProperties ----

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
}
