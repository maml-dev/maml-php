# maml-php

[MAML](https://maml.dev) parser for PHP. Includes a full AST with source positions, comment preservation, and pretty printing.

- Spec-accurate parser and serializer
- Full AST with source positions (offset, line, column) on every node
- Comments preserved and attached to nearest nodes
- `printAst()` reconstructs source from AST, including comments
- `errorSnippet()` for user-friendly error messages pointing at source locations
- Zero dependencies
- 100% test coverage

## Installation

```
composer require maml/maml
```

Requires PHP 8.2+ with `mbstring`.

## Quick Start

```php
use Maml\Maml;

// Parse to plain PHP values
$data = Maml::parse('{name: "MAML", version: 1}');
$data['name']; // "MAML"

// Serialize back to MAML
Maml::stringify(['name' => 'MAML', 'version' => 1]);
// {
//   name: "MAML"
//   version: 1
// }
```

## AST

```php
$source = '{
  # Database config
  host: "localhost"
  port: 5432
}';

$doc = Maml::parseAst($source);
```

Every node has a `type` string and a `span` with start/end positions:

```php
$doc->value->type; // "Object"
$doc->value->span->start->line; // 1
$doc->value->properties[0]->key->value; // "host"
```

### Printing

`printAst()` reconstructs MAML source from an AST, preserving comments and blank lines:

```php
Maml::printAst($doc);
// {
//   # Database config
//   host: "localhost"
//   port: 5432
// }
```

### Converting to plain values

`toValue()` strips AST metadata and returns plain PHP values:

```php
Maml::toValue($doc); // ["host" => "localhost", "port" => 5432]
```

### Error snippets

Point at any AST node in source for user-friendly error messages:

```php
$node = $doc->value->properties[1]->value;
Maml::errorSnippet($source, $node->span->start, 'Port out of range');
// Port out of range on line 4.
//
//       port: 5432
//     ........^
```

## Node Types

| Node       | `type`         | `value`  | `raw` |
|------------|----------------|----------|-------|
| String     | `"String"`     | `string` | yes   |
| Raw String | `"RawString"`  | `string` | yes   |
| Integer    | `"Integer"`    | `int`    | yes   |
| Float      | `"Float"`      | `float`  | yes   |
| Boolean    | `"Boolean"`    | `bool`   | --    |
| Null       | `"Null"`       | `null`   | --    |
| Object     | `"Object"`     | `properties: Property[]` | -- |
| Array      | `"Array"`      | `elements: Element[]`    | -- |

Object keys are either `IdentifierKey` for bare keys like `host`, or `StringNode` for quoted keys like `"host name"`.

### Comments

Comments are attached to the nearest node:

- **`Property.leadingComments`** / **`Element.leadingComments`** -- comments on lines before
- **`Property.trailingComment`** / **`Element.trailingComment`** -- comment on the same line after
- **`ObjectNode.danglingComments`** / **`ArrayNode.danglingComments`** -- comments inside empty containers or after the last entry
- **`Document.leadingComments`** / **`Document.danglingComments`** -- comments before/after the root value

### Blank lines

`Property.emptyLineBefore` and `Element.emptyLineBefore` are `true` when there is a blank line separating from the previous entry.

## License

[MIT](LICENSE)
