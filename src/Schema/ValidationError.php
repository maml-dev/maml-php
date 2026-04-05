<?php

declare(strict_types=1);

namespace Maml\Schema;

use Maml\Ast\Span;

readonly class ValidationError
{
    public function __construct(
        public string $message,
        public string $path,
        public ?Span $span,
    ) {}
}
