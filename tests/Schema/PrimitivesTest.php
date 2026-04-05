<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Schema\S;
use PHPUnit\Framework\TestCase;

final class PrimitivesTest extends TestCase
{
    use ValidatorTestTrait;

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
}
