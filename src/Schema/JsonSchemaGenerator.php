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

final class JsonSchemaGenerator
{
    /**
     * @return array<string, mixed>
     */
    public static function generate(SchemaType $schema): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            ...self::convert($schema),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function convert(SchemaType $schema): array
    {
        if ($schema instanceof OptionalType) {
            return self::convert($schema->inner);
        }

        if ($schema instanceof AnyType) {
            return [];
        }

        if ($schema instanceof StringType) {
            $result = ['type' => 'string'];
            if ($schema->pattern !== null) {
                // Strip PHP delimiters (e.g. "/^foo$/" → "^foo$")
                $result['pattern'] = self::stripDelimiters($schema->pattern);
            }
            return $result;
        }

        if ($schema instanceof IntegerType) {
            $result = ['type' => 'integer'];
            if ($schema->min !== null) {
                $result['minimum'] = $schema->min;
            }
            if ($schema->max !== null) {
                $result['maximum'] = $schema->max;
            }
            return $result;
        }

        if ($schema instanceof FloatType) {
            $result = ['type' => 'number'];
            if ($schema->min !== null) {
                $result['minimum'] = $schema->min;
            }
            if ($schema->max !== null) {
                $result['maximum'] = $schema->max;
            }
            return $result;
        }

        if ($schema instanceof NumberType) {
            $result = ['type' => 'number'];
            if ($schema->min !== null) {
                $result['minimum'] = $schema->min;
            }
            if ($schema->max !== null) {
                $result['maximum'] = $schema->max;
            }
            return $result;
        }

        if ($schema instanceof BooleanType) {
            return ['type' => 'boolean'];
        }

        if ($schema instanceof NullType) {
            return ['type' => 'null'];
        }

        if ($schema instanceof LiteralType) {
            return ['const' => $schema->value];
        }

        if ($schema instanceof ObjectType || $schema instanceof OrderedObjectType) {
            return self::convertObject($schema->properties, $schema->additionalProperties);
        }

        if ($schema instanceof MapType) {
            $result = ['type' => 'object'];
            if ($schema->values instanceof AnyType) {
                $result['additionalProperties'] = true;
            } else {
                $result['additionalProperties'] = self::convert($schema->values);
            }
            return $result;
        }

        if ($schema instanceof ArrayOfType) {
            $result = [
                'type' => 'array',
                'items' => self::convert($schema->items),
            ];
            if ($schema->minItems !== null) {
                $result['minItems'] = $schema->minItems;
            }
            if ($schema->maxItems !== null) {
                $result['maxItems'] = $schema->maxItems;
            }
            return $result;
        }

        if ($schema instanceof TupleType) {
            return [
                'type' => 'array',
                'items' => \array_map(fn(SchemaType $s) => self::convert($s), $schema->elements),
                'minItems' => \count($schema->elements),
                'maxItems' => \count($schema->elements),
            ];
        }

        // $schema is UnionType at this point
        /** @var UnionType $schema */
        return [
            'oneOf' => \array_map(fn(SchemaType $s) => self::convert($s), $schema->branches),
        ];
    }

    /**
     * @param array<string, SchemaType> $properties
     * @return array<string, mixed>
     */
    private static function convertObject(array $properties, ?SchemaType $additionalProperties): array
    {
        $result = ['type' => 'object'];

        $props = [];
        $required = [];
        foreach ($properties as $key => $propSchema) {
            if ($propSchema instanceof OptionalType) {
                $props[$key] = self::convert($propSchema->inner);
            } else {
                $props[$key] = self::convert($propSchema);
                $required[] = $key;
            }
        }

        if ($props !== []) {
            $result['properties'] = $props;
        }
        if ($required !== []) {
            $result['required'] = $required;
        }

        if ($additionalProperties === null) {
            $result['additionalProperties'] = false;
        } elseif (!($additionalProperties instanceof AnyType)) {
            $result['additionalProperties'] = self::convert($additionalProperties);
        }

        return $result;
    }

    private static function stripDelimiters(string $pattern): string
    {
        if (\strlen($pattern) >= 2) {
            $delimiter = $pattern[0];
            $lastPos = \strrpos($pattern, $delimiter, 1);
            if ($lastPos !== false) {
                return \substr($pattern, 1, $lastPos - 1);
            }
        }
        return $pattern;
    }
}
