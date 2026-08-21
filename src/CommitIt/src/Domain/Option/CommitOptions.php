<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\Repository::commit()}.
 *
 * @api
 */
final class CommitOptions
{
    private function __construct(
        public readonly ?string $authorName,
        public readonly ?string $authorEmail,
        public readonly ?\DateTimeImmutable $authorDate,
        public readonly bool $allowEmpty,
        public readonly bool $amend,
    ) {
    }

    public static function default(): self
    {
        return new self(null, null, null, false, false);
    }

    public function withAuthor(?string $name, ?string $email): self
    {
        return new self($name, $email, $this->authorDate, $this->allowEmpty, $this->amend);
    }

    public function withAuthorDate(\DateTimeImmutable $date): self
    {
        return new self($this->authorName, $this->authorEmail, $date, $this->allowEmpty, $this->amend);
    }

    public function withAllowEmpty(bool $allowEmpty = true): self
    {
        return new self($this->authorName, $this->authorEmail, $this->authorDate, $allowEmpty, $this->amend);
    }

    public function withAmend(bool $amend = true): self
    {
        return new self($this->authorName, $this->authorEmail, $this->authorDate, $this->allowEmpty, $amend);
    }
}
