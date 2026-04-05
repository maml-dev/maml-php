<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Schema\S;
use PHPUnit\Framework\TestCase;

final class ConstraintsTest extends TestCase
{
    use ValidatorTestTrait;

    // ---- Integer range ----

    public function testIntegerMinValid(): void
    {
        $this->assertValid('5', S::integer(min: 0));
    }

    public function testIntegerMinInvalid(): void
    {
        $errors = $this->validate('-1', S::integer(min: 0));
        $this->assertCount(1, $errors);
        $this->assertSame('Value -1 is less than minimum 0', $errors[0]->message);
    }

    public function testIntegerMaxValid(): void
    {
        $this->assertValid('80', S::integer(max: 65535));
    }

    public function testIntegerMaxInvalid(): void
    {
        $errors = $this->validate('70000', S::integer(max: 65535));
        $this->assertCount(1, $errors);
        $this->assertSame('Value 70000 is greater than maximum 65535', $errors[0]->message);
    }

    public function testIntegerRangeBothBounds(): void
    {
        $this->assertValid('8080', S::integer(min: 1, max: 65535));

        $errors = $this->validate('0', S::integer(min: 1, max: 65535));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('less than minimum', $errors[0]->message);
    }

    public function testIntegerRangeTypeErrorBeforeRange(): void
    {
        $errors = $this->validate('"x"', S::integer(min: 0));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected integer, got string', $errors[0]->message);
    }

    // ---- Float range ----

    public function testFloatMinValid(): void
    {
        $this->assertValid('0.5', S::float(min: 0.0));
    }

    public function testFloatMinInvalid(): void
    {
        $errors = $this->validate('-0.1', S::float(min: 0.0));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('less than minimum', $errors[0]->message);
    }

    public function testFloatMaxInvalid(): void
    {
        $errors = $this->validate('1.5', S::float(max: 1.0));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('greater than maximum', $errors[0]->message);
    }

    // ---- Number range ----

    public function testNumberRangeAcceptsIntegerInRange(): void
    {
        $this->assertValid('5', S::number(min: 0, max: 10));
    }

    public function testNumberRangeAcceptsFloatInRange(): void
    {
        $this->assertValid('3.14', S::number(min: 0, max: 10));
    }

    public function testNumberRangeRejectsOutOfRange(): void
    {
        $errors = $this->validate('11', S::number(min: 0, max: 10));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('greater than maximum', $errors[0]->message);
    }

    // ---- String pattern ----

    public function testStringPatternValid(): void
    {
        $this->assertValid('"2024-01-15"', S::string(pattern: '/^\d{4}-\d{2}-\d{2}$/'));
    }

    public function testStringPatternInvalid(): void
    {
        $errors = $this->validate('"not-a-date"', S::string(pattern: '/^\d{4}-\d{2}-\d{2}$/'));
        $this->assertCount(1, $errors);
        $this->assertSame('String does not match pattern /^\d{4}-\d{2}-\d{2}$/', $errors[0]->message);
    }

    public function testStringPatternTypeErrorBeforePattern(): void
    {
        $errors = $this->validate('42', S::string(pattern: '/./'));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
    }

    public function testStringPatternOnRawString(): void
    {
        $this->assertValid('"""hello"""', S::string(pattern: '/^hello$/'));
    }

    // ---- Array minItems/maxItems ----

    public function testArrayOfMinItemsValid(): void
    {
        $this->assertValid('[1, 2]', S::arrayOf(S::integer(), minItems: 1));
    }

    public function testArrayOfMinItemsInvalid(): void
    {
        $errors = $this->validate('[]', S::arrayOf(S::integer(), minItems: 1));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected at least 1 items, got 0', $errors[0]->message);
    }

    public function testArrayOfMaxItemsValid(): void
    {
        $this->assertValid('[1, 2]', S::arrayOf(S::integer(), maxItems: 3));
    }

    public function testArrayOfMaxItemsInvalid(): void
    {
        $errors = $this->validate('[1, 2, 3, 4]', S::arrayOf(S::integer(), maxItems: 3));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected at most 3 items, got 4', $errors[0]->message);
    }

    public function testArrayOfMinMaxCombined(): void
    {
        $this->assertValid('[1, 2, 3]', S::arrayOf(S::integer(), minItems: 1, maxItems: 5));

        $errors = $this->validate('[]', S::arrayOf(S::integer(), minItems: 1, maxItems: 5));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('at least 1', $errors[0]->message);
    }

    public function testArrayOfMinItemsStillValidatesElements(): void
    {
        $errors = $this->validate('["a", 42]', S::arrayOf(S::string(), minItems: 1));
        $this->assertCount(1, $errors);
        $this->assertSame('Expected string, got integer', $errors[0]->message);
        $this->assertSame('$[1]', $errors[0]->path);
    }

    // ---- describe() ----

    public function testConstrainedDescribe(): void
    {
        $this->assertSame('integer(min: 0)', S::integer(min: 0)->describe());
        $this->assertSame('integer(max: 100)', S::integer(max: 100)->describe());
        $this->assertSame('integer(min: 0, max: 100)', S::integer(min: 0, max: 100)->describe());
        $this->assertSame('float(min: 0)', S::float(min: 0.0)->describe());
        $this->assertSame('float(min: 0, max: 1)', S::float(min: 0.0, max: 1.0)->describe());
        $this->assertSame('number(min: 0, max: 10)', S::number(min: 0, max: 10)->describe());
        $this->assertSame('string(pattern: /^\w+$/)', S::string(pattern: '/^\w+$/')->describe());
        $this->assertSame('string[](minItems: 1)', S::arrayOf(S::string(), minItems: 1)->describe());
        $this->assertSame('string[](maxItems: 5)', S::arrayOf(S::string(), maxItems: 5)->describe());
        $this->assertSame('string[](minItems: 1, maxItems: 5)', S::arrayOf(S::string(), minItems: 1, maxItems: 5)->describe());
    }
}
