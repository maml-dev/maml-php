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

## License

[MIT](LICENSE)
