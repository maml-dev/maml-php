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

Point at any AST node in source for user-friendly error messages. Accepts `Position` (single `^`) or `Span` (underline `^^^^`):

```php
$node = $doc->value->properties[1]->value;
Maml::errorSnippet($source, $node->span, 'Port out of range');
// Port out of range on line 4.
//
//       port: 5432
//             ^^^^
```

Show context lines and Rust-style gutter with line numbers:

```php
Maml::errorSnippet($source, $node->span, 'Port out of range', context: 2, gutter: true);
// Port out of range on line 4.
//
//     2 |   host: "localhost"
//     3 |   port: 5432
//     4 |   timeout: -1
//       |            ^^
```

Options: `context` (lines above), `indent` (default `'    '`), `gutter` (line numbers).

## Schema Validation

Define expected shapes with the `S` builder, validate against a parsed AST:

```php
use Maml\Schema\S;

$schema = S::object([
    'host' => S::string(),
    'port' => S::integer(min: 1, max: 65535),
    'tags' => S::arrayOf(S::string(), minItems: 1),
    'ssl' => S::optional(S::boolean()),
    'mode' => S::enum('fast', 'safe', 'auto'),
    'version' => S::optional(S::string(pattern: '/^\d+\.\d+\.\d+$/')),
]);

$doc = Maml::parseAst($source);
$errors = Maml::validate($doc, $schema);

foreach ($errors as $error) {
    // $error->message  "Expected integer, got string"
    // $error->path     "$.port"
    // $error->span     Span(start: Position(line: 3, ...), end: ...)
    echo Maml::errorSnippet($source, $error->span, $error->message);
}
```

### Available schema types

| Builder                            | Matches                                            |
|------------------------------------|----------------------------------------------------|
| `S::string()`                      | String or raw string                               |
| `S::string(pattern: '/.../')`      | String matching regex                              |
| `S::integer()`                     | Integer                                            |
| `S::integer(min: 0, max: 100)`     | Integer within range                               |
| `S::float()`                       | Float                                              |
| `S::float(min: 0.0, max: 1.0)`     | Float within range                                 |
| `S::number()`                      | Integer or float                                   |
| `S::number(min: 0)`                | Number with minimum                                |
| `S::boolean()`                     | Boolean                                            |
| `S::null()`                        | Null                                               |
| `S::any()`                         | Anything                                           |
| `S::literal('x')`                  | Exact value                                        |
| `S::enum('a', 'b')`                | One of the listed values                           |
| `S::object([...])`                 | Object with typed properties, rejects unknown keys |
| `S::object([...], S::any())`       | Same, but allows extra keys                        |
| `S::object([...], S::string())`    | Same, extra keys must match schema                 |
| `S::orderedObject([...])`          | Object with properties in order                    |
| `S::map(schema)`                   | Object with any keys, typed values                 |
| `S::optional(schema)`              | Property may be absent                             |
| `S::arrayOf(schema)`               | Array of uniform type                              |
| `S::arrayOf(schema, minItems: 1)`  | Array with minimum length                          |
| `S::arrayOf(schema, maxItems: 10)` | Array with maximum length                          |
| `S::tuple([s1, s2])`               | Fixed-length array                                 |
| `S::union(s1, s2)`                 | One of several schemas                             |

## License

[MIT](LICENSE)
