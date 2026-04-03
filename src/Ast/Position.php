<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class Position
{
    public function __construct(
        public int $offset, // 0-based byte offset
        public int $line,   // 1-based
        public int $column, // 1-based
    ) {}
}
