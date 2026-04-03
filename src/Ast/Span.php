<?php

declare(strict_types=1);

namespace Maml\Ast;

readonly class Span
{
    public function __construct(
        public Position $start,
        public Position $end, // exclusive (one past last char)
    ) {}
}
