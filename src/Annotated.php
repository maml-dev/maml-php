<?php

declare(strict_types=1);

namespace Maml;

final class Annotated
{
    /** @var string[] */
    public readonly array $leadingComments;
    public readonly ?string $trailingComment;
    public readonly bool $emptyLineBefore;
    /** @var string[] */
    public readonly array $danglingComments;

    /**
     * @param string[] $leadingComments
     * @param string[] $danglingComments
     */
    private function __construct(
        public readonly mixed $value,
        array $leadingComments = [],
        ?string $trailingComment = null,
        bool $emptyLineBefore = false,
        array $danglingComments = [],
    ) {
        $this->leadingComments = $leadingComments;
        $this->trailingComment = $trailingComment;
        $this->emptyLineBefore = $emptyLineBefore;
        $this->danglingComments = $danglingComments;
    }

    public static function with(mixed $value): self
    {
        return new self($value);
    }

    public function comment(string ...$comments): self
    {
        return new self(
            $this->value,
            [...$this->leadingComments, ...$comments],
            $this->trailingComment,
            $this->emptyLineBefore,
            $this->danglingComments,
        );
    }

    public function trailingComment(string $comment): self
    {
        return new self(
            $this->value,
            $this->leadingComments,
            $comment,
            $this->emptyLineBefore,
            $this->danglingComments,
        );
    }

    public function emptyLineBefore(): self
    {
        return new self(
            $this->value,
            $this->leadingComments,
            $this->trailingComment,
            true,
            $this->danglingComments,
        );
    }

    public function danglingComment(string ...$comments): self
    {
        return new self(
            $this->value,
            $this->leadingComments,
            $this->trailingComment,
            $this->emptyLineBefore,
            [...$this->danglingComments, ...$comments],
        );
    }
}
