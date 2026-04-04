<?php

declare(strict_types=1);

namespace Maml\Schema;

use Maml\Ast\Position;

readonly class ValidationError
{
    public function __construct(
        public string $message,
        public string $path,
        public ?Position $position,
    ) {}
}
