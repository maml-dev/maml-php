<?php

declare(strict_types=1);

namespace Maml;

final class Maml
{
    public static function parse(string $source): mixed
    {
        return Parser::parse($source);
    }

    public static function stringify(mixed $value): string
    {
        return Stringifier::stringify($value);
    }
}
