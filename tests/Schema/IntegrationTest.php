<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Schema\S;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    use ValidatorTestTrait;

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

    // ---- Span tracking ----

    public function testErrorSpanPointsAtNode(): void
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
        $this->assertGreaterThan(0, \count($errors));
    }

    // ---- Schema describe() ----

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
}
