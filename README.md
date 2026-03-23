# maml-php

A modern, well‑tested implementation of the [MAML](https://maml.dev) data format for PHP.

- Spec‑accurate parser and pretty serializer
- Zero dependencies
- 100% test coverage (classes, methods, lines)

## Installation

```
composer require maml/maml
```

## Usage

```php
use Maml\Maml;

$data = Maml::parse('{
  project: "MAML"
  tags: [
    "minimal"
    "readable"
  ]

  # A simple nested object
  spec: {
    version: 1
    author: "Anton Medvedev"
  }

  notes: """
This is a raw multiline string.
Keeps formatting as‑is.
"""
}');

echo $data['project']; // "MAML"

$text = Maml::stringify(['foo' => 'bar', 'list' => [1, 2, 3]]);
/*
{
  foo: "bar"
  list: [
    1
    2
    3
  ]
}
*/
```

## License

[MIT](LICENSE)
