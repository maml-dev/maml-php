<?php

declare(strict_types=1);

namespace Maml\Schema;

use Maml\Ast\ArrayNode;
use Maml\Ast\BooleanNode;
use Maml\Ast\Document;
use Maml\Ast\FloatNode;
use Maml\Ast\IntegerNode;
use Maml\Ast\NullNode;
use Maml\Ast\ObjectNode;
use Maml\Ast\RawStringNode;
use Maml\Ast\Span;
use Maml\Ast\StringNode;
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

final class Validator
{
    /** @var ValidationError[] */
    private array $errors = [];

    /**
     * @return ValidationError[]
     */
    public static function validate(Document $doc, SchemaType $schema): array
    {
        $validator = new self();
        $validator->validateNode($doc->value, $schema, '$');
        return $validator->errors;
    }

    private function validateNode(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        SchemaType $schema,
        string $path,
    ): void {
        // Unwrap OptionalType outside of object context
        if ($schema instanceof OptionalType) {
            $this->validateNode($node, $schema->inner, $path);
            return;
        }

        if ($schema instanceof AnyType) {
            return;
        }

        if ($schema instanceof StringType) {
            if (!($node instanceof StringNode) && !($node instanceof RawStringNode)) {
                $this->addError('Expected string, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof IntegerType) {
            if (!($node instanceof IntegerNode)) {
                $this->addError('Expected integer, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof FloatType) {
            if (!($node instanceof FloatNode)) {
                $this->addError('Expected float, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof NumberType) {
            if (!($node instanceof IntegerNode) && !($node instanceof FloatNode)) {
                $this->addError('Expected number, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof BooleanType) {
            if (!($node instanceof BooleanNode)) {
                $this->addError('Expected boolean, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof NullType) {
            if (!($node instanceof NullNode)) {
                $this->addError('Expected null, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if ($schema instanceof LiteralType) {
            $this->validateLiteral($node, $schema, $path);
            return;
        }

        if ($schema instanceof ObjectType) {
            $this->validateObject($node, $schema->properties, $schema->additionalProperties, $path);
            return;
        }

        if ($schema instanceof OrderedObjectType) {
            $this->validateObject($node, $schema->properties, $schema->additionalProperties, $path);
            if ($node instanceof ObjectNode) {
                $this->validateOrder($node, $schema->properties, $path);
            }
            return;
        }

        if ($schema instanceof MapType) {
            if (!($node instanceof ObjectNode)) {
                $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNode($node), $path, $node->span);
                return;
            }
            foreach ($node->properties as $prop) {
                $this->validateNode($prop->value, $schema->values, $path . '.' . $prop->key->value);
            }
            return;
        }

        if ($schema instanceof ArrayOfType) {
            if (!($node instanceof ArrayNode)) {
                $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNode($node), $path, $node->span);
                return;
            }
            foreach ($node->elements as $i => $element) {
                $this->validateNode($element->value, $schema->items, $path . '[' . $i . ']');
            }
            return;
        }

        if ($schema instanceof TupleType) {
            if (!($node instanceof ArrayNode)) {
                $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNode($node), $path, $node->span);
                return;
            }
            $expected = \count($schema->elements);
            $actual = \count($node->elements);
            if ($actual !== $expected) {
                $this->addError(
                    'Expected array of length ' . $expected . ', got ' . $actual,
                    $path,
                    $node->span,
                );
            }
            $len = \min($expected, $actual);
            for ($i = 0; $i < $len; $i++) {
                $this->validateNode($node->elements[$i]->value, $schema->elements[$i], $path . '[' . $i . ']');
            }
            return;
        }

        if ($schema instanceof UnionType) {
            $this->validateUnion($node, $schema, $path);
            return;
        }
    }

    private function validateLiteral(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        LiteralType $schema,
        string $path,
    ): void {
        $expected = $schema->value;

        if ($expected === null) {
            if (!($node instanceof NullNode)) {
                $this->addError('Expected null, got ' . self::describeNode($node), $path, $node->span);
            }
            return;
        }

        if (\is_bool($expected)) {
            if (!($node instanceof BooleanNode) || $node->value !== $expected) {
                $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNodeValue($node), $path, $node->span);
            }
            return;
        }

        if (\is_string($expected)) {
            if (($node instanceof StringNode || $node instanceof RawStringNode) && $node->value === $expected) {
                return;
            }
            $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNodeValue($node), $path, $node->span);
            return;
        }

        if (\is_int($expected)) {
            if ($node instanceof IntegerNode && $node->value === $expected) {
                return;
            }
            $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNodeValue($node), $path, $node->span);
            return;
        }

        // float
        if ($node instanceof FloatNode && $node->value === $expected) {
            return;
        }
        $this->addError('Expected ' . $schema->describe() . ', got ' . self::describeNodeValue($node), $path, $node->span);
    }

    /**
     * @param array<string, SchemaType> $properties
     */
    private function validateObject(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        array $properties,
        ?SchemaType $additionalProperties,
        string $path,
    ): void {
        if (!($node instanceof ObjectNode)) {
            $desc = 'object{' . \implode(', ', \array_keys($properties)) . '}';
            $this->addError('Expected ' . $desc . ', got ' . self::describeNode($node), $path, $node->span);
            return;
        }

        // Build map of actual keys
        $actual = [];
        foreach ($node->properties as $prop) {
            $actual[$prop->key->value] = $prop;
        }

        // Unknown keys: reject or validate against additionalProperties schema
        foreach ($actual as $key => $prop) {
            if (!isset($properties[$key])) {
                if ($additionalProperties === null) {
                    $this->addError('Unknown property "' . $key . '"', $path . '.' . $key, $prop->key->span);
                } else {
                    $this->validateNode($prop->value, $additionalProperties, $path . '.' . $key);
                }
            }
        }

        // Missing required keys + recurse into present values
        foreach ($properties as $key => $propSchema) {
            $isOptional = $propSchema instanceof OptionalType;
            $innerSchema = $isOptional ? $propSchema->inner : $propSchema;

            if (isset($actual[$key])) {
                $this->validateNode($actual[$key]->value, $innerSchema, $path . '.' . $key);
            } elseif (!$isOptional) {
                $this->addError('Missing required property "' . $key . '"', $path . '.' . $key, $node->span);
            }
        }
    }

    /**
     * @param array<string, SchemaType> $properties
     */
    private function validateOrder(ObjectNode $node, array $properties, string $path): void
    {
        $schemaKeys = \array_keys($properties);

        // Collect actual keys in AST order, filtered to schema-defined keys only
        $actualOrder = [];
        foreach ($node->properties as $prop) {
            $key = $prop->key->value;
            if (isset($properties[$key])) {
                $actualOrder[] = $key;
            }
        }

        // Expected order: schema keys filtered to only those present
        $presentSet = \array_flip($actualOrder);
        $expectedOrder = \array_filter($schemaKeys, fn(string $k) => isset($presentSet[$k]));
        $expectedOrder = \array_values($expectedOrder);

        if ($actualOrder !== $expectedOrder) {
            $this->addError(
                'Properties are not in the expected order. Expected: ' . \implode(', ', $expectedOrder),
                $path,
                $node->span,
            );
        }
    }

    private function validateUnion(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
        UnionType $schema,
        string $path,
    ): void {
        foreach ($schema->branches as $branch) {
            $sub = new self();
            $sub->validateNode($node, $branch, $path);
            if ($sub->errors === []) {
                return;
            }
        }
        $this->addError(
            'Expected ' . $schema->describe() . ', got ' . self::describeNode($node),
            $path,
            $node->span,
        );
    }

    private function addError(string $message, string $path, ?Span $span): void
    {
        $this->errors[] = new ValidationError($message, $path, $span);
    }

    private static function describeNode(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
    ): string {
        return match (true) {
            $node instanceof StringNode, $node instanceof RawStringNode => 'string',
            $node instanceof IntegerNode => 'integer',
            $node instanceof FloatNode => 'float',
            $node instanceof BooleanNode => 'boolean',
            $node instanceof NullNode => 'null',
            $node instanceof ObjectNode => 'object',
            $node instanceof ArrayNode => 'array',
        };
    }

    private static function describeNodeValue(
        StringNode|RawStringNode|IntegerNode|FloatNode|BooleanNode|NullNode|ObjectNode|ArrayNode $node,
    ): string {
        return match (true) {
            $node instanceof StringNode, $node instanceof RawStringNode => 'string "' . $node->value . '"',
            $node instanceof IntegerNode => 'integer ' . $node->value,
            $node instanceof FloatNode => 'float ' . $node->value,
            $node instanceof BooleanNode => $node->value ? 'true' : 'false',
            $node instanceof NullNode => 'null',
            $node instanceof ObjectNode => 'object',
            $node instanceof ArrayNode => 'array',
        };
    }
}
