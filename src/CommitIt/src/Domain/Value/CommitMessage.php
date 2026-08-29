<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use ItPack\CommitIt\Domain\Validation\CommitMessageValidator;

/**
 * Immutable commit message composed of a subject and an optional body.
 *
 * Construction routes through {@see CommitMessageValidator}: the subject must be
 * non-empty, a single line, and within the length guard; the body is optional
 * and normalized to null when empty.
 *
 * @api
 */
final readonly class CommitMessage
{
    private function __construct(
        public readonly string $subject,
        public readonly ?string $body,
    ) {
    }

    public static function fromParts(string $subject, ?string $body = null): self
    {
        $trimmedSubject = trim($subject);
        $trimmedBody = $body === null ? null : trim($body);

        CommitMessageValidator::assertValid($trimmedSubject, $trimmedBody);

        return new self($trimmedSubject, $trimmedBody);
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
