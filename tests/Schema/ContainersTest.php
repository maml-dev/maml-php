<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Schema\S;
use PHPUnit\Framework\TestCase;

final class ContainersTest extends TestCase
{
    use ValidatorTestTrait;

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

    public function testUnionReportsDeepestBranchErrors(): void
    {
        $step = S::object([
            'run' => S::optional(S::string()),
        ], S::any());

        $schema = S::map(S::union(
            S::arrayOf($step),
            S::arrayOf(S::string()),
        ));

        $errors = $this->validate('{build: [{run: "ok"}, {run: 42}]}', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$.build[1].run', $errors[0]->path);
    }

    public function testUnionGenericWhenNoBranchMatchesDeeper(): void
    {
        $errors = $this->validate('true', S::union(S::string(), S::integer()));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string | integer, got boolean', $errors[0]->message);
    }

    public function testUnionPrefersObjectBranchOverNull(): void
    {
        $schema = S::union(
            S::object(['name' => S::string()], S::any()),
            S::null(),
        );
        $errors = $this->validate('{name: 42}', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$.name', $errors[0]->path);
    }

    public function testUnionPicksFewestErrorsOnTiedDepth(): void
    {
        $schema = S::union(
            S::arrayOf(S::object(['a' => S::string()])),
            S::arrayOf(S::object(['a' => S::integer(), 'b' => S::integer()])),
        );
        $errors = $this->validate('[{a: 42}]', $schema);
        $this->assertCount(1, $errors);
        $this->assertSame('$[0].a', $errors[0]->path);
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
}
