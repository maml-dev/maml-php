<?php

declare(strict_types=1);

namespace Maml\Tests\Schema;

use Maml\Maml;
use Maml\Schema\SchemaType;
use Maml\Schema\ValidationError;

trait ValidatorTestTrait
{
    /**
     * @return ValidationError[]
     */
    private function validate(string $source, SchemaType $schema): array
    {
        $doc = Maml::parseAst($source);
        return Maml::validate($doc, $schema);
    }

    private function assertValid(string $source, SchemaType $schema): void
    {
        $errors = $this->validate($source, $schema);
        $messages = \array_map(fn(ValidationError $e) => $e->path . ': ' . $e->message, $errors);
        $this->assertSame([], $messages);
    }
}
