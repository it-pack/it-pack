<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Immutable commit message composed of a subject and an optional body.
 *
 * Skeleton for Milestone M1: splits a raw message on the first blank line and
 * enforces a non-empty subject. Richer rules (length, whitespace) are enforced
 * by {@see \ItPack\CommitIt\Domain\Validation\CommitMessageValidator} in M2.
 *
 * @api
 */
final class CommitMessage
{
    private function __construct(
        public readonly string $subject,
        public readonly ?string $body,
    ) {
    }

    public static function fromParts(string $subject, ?string $body = null): self
    {
        $trimmedSubject = trim($subject);

        if ($trimmedSubject === '') {
            throw new \InvalidArgumentException('Commit subject must not be empty.');
        }

        $trimmedBody = $body === null ? null : trim($body);

        return new self($trimmedSubject, $trimmedBody === '' ? null : $trimmedBody);
    }

    public static function fromString(string $message): self
    {
        $parts = preg_split('/\R\R/', $message, 2);

        $subject = trim($parts[0] ?? '');
        $body = isset($parts[1]) ? trim($parts[1]) : null;

        return self::fromParts($subject, $body);
    }

    public function toString(): string
    {
        return $this->body === null
            ? $this->subject
            : $this->subject . "\n\n" . $this->body;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
