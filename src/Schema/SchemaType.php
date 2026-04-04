<?php

declare(strict_types=1);

namespace Maml\Schema;

interface SchemaType
{
    public function describe(): string;
}
