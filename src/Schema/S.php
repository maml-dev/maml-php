<?php

declare(strict_types=1);

namespace Maml\Schema;

use Maml\Schema\Type\AnyType;
use Maml\Schema\Type\ArrayOfType;
use Maml\Schema\Type\BooleanType;
use Maml\Schema\Type\FloatType;
use Maml\Schema\Type\IntegerType;
use Maml\Schema\Type\LiteralType;
use Maml\Schema\Type\MapType;
use Maml\Schema\Type\NullType;
use Maml\Schema\Type\NumberType;
use Maml\Schema\Type\ObjectType;
use Maml\Schema\Type\OptionalType;
use Maml\Schema\Type\OrderedObjectType;
use Maml\Schema\Type\StringType;
use Maml\Schema\Type\TupleType;
use Maml\Schema\Type\UnionType;

final class S
{
    public static function string(): StringType
    {
        return new StringType();
    }

    public static function integer(): IntegerType
    {
        return new IntegerType();
    }

    public static function float(): FloatType
    {
        return new FloatType();
    }

    public static function number(): NumberType
    {
        return new NumberType();
    }

    public static function boolean(): BooleanType
    {
        return new BooleanType();
    }

    public static function null(): NullType
    {
        return new NullType();
    }

    public static function literal(string|int|float|bool|null $value): LiteralType
    {
        return new LiteralType($value);
    }

    /** @param array<string, SchemaType> $properties */
    public static function object(array $properties): ObjectType
    {
        return new ObjectType($properties);
    }

    /** @param array<string, SchemaType> $properties */
    public static function orderedObject(array $properties): OrderedObjectType
    {
        return new OrderedObjectType($properties);
    }

    public static function optional(SchemaType $schema): OptionalType
    {
        return new OptionalType($schema);
    }

    public static function arrayOf(SchemaType $items): ArrayOfType
    {
        return new ArrayOfType($items);
    }

    /** @param SchemaType[] $elements */
    public static function tuple(array $elements): TupleType
    {
        return new TupleType($elements);
    }

    public static function map(SchemaType $values): MapType
    {
        return new MapType($values);
    }

    public static function union(SchemaType ...$branches): UnionType
    {
        return new UnionType($branches);
    }

    public static function any(): AnyType
    {
        return new AnyType();
    }

    public static function enum(string|int|float|bool|null ...$values): UnionType
    {
        return new UnionType(
            \array_map(fn($v) => new LiteralType($v), $values),
        );
    }
}
