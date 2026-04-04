# maml-php

[MAML](https://maml.dev) parser for PHP. Includes a full AST with source positions, comment preservation, and pretty printing.

- Spec-accurate parser and serializer
- Full AST with source positions (offset, line, column) on every node
- Comments preserved and attached to nearest nodes
- Schema validation with detailed error reporting
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

## Schema Validation

Define expected shapes with the `S` builder, validate against a parsed AST:

```php
use Maml\Schema\S;

$schema = S::object([
    'host' => S::string(),
    'port' => S::integer(),
    'tags' => S::arrayOf(S::string()),
    'ssl' => S::optional(S::boolean()),
    'mode' => S::enum('fast', 'safe', 'auto'),
]);

$doc = Maml::parseAst($source);
$errors = Maml::validate($doc, $schema);

foreach ($errors as $error) {
    // $error->message  "Missing required property "host""
    // $error->path     "$.host"
    // $error->position Position(line: 1, column: 1)
    echo Maml::errorSnippet($source, $error->position, $error->message);
}
```

### Available schema types

| Builder | Matches |
|---------|---------|
| `S::string()` | String or raw string |
| `S::integer()` | Integer |
| `S::float()` | Float |
| `S::number()` | Integer or float |
| `S::boolean()` | Boolean |
| `S::null()` | Null |
| `S::any()` | Anything |
| `S::literal('x')` | Exact value |
| `S::enum('a', 'b')` | One of the listed values |
| `S::object([...])` | Object with typed properties |
| `S::orderedObject([...])` | Object with properties in order |
| `S::optional(schema)` | Property may be absent |
| `S::arrayOf(schema)` | Array of uniform type |
| `S::tuple([s1, s2])` | Fixed-length array |
| `S::union(s1, s2)` | One of several schemas |

## License

[MIT](LICENSE)
