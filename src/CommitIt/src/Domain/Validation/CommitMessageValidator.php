<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Validation;

use ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException;

/**
 * Pure, dependency-free validator for commit messages.
 *
 * Enforces a non-empty, single-line, bounded-length subject. The body is
 * optional; when provided it must be non-empty (callers are expected to trim
 * before passing). The subject length guard prevents unbounded input from
 * reaching the backend.
 */
final readonly class CommitMessageValidator
{
    public const MAX_SUBJECT_LENGTH = 72;

    /**
     * Returns a human-readable reason the message is invalid, or null when valid.
     */
    public static function reason(string $subject, ?string $body): ?string
    {
        $subject = trim($subject);

        if ($subject === '') {
            return 'The commit subject must not be empty.';
        }

        if (str_contains($subject, "\n")) {
            return 'The commit subject must be a single line.';
        }

        if (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            return sprintf('The commit subject must not exceed %d characters.', self::MAX_SUBJECT_LENGTH);
        }

        if ($body !== null) {
            $body = trim($body);

            if ($body === '') {
                return 'The commit body must not be empty when provided.';
            }
        }

        return null;
    }

    /**
     * Validates the message parts, throwing a domain exception on failure.
     *
     * @throws InvalidCommitMessageException
     */
    public static function assertValid(string $subject, ?string $body): void
    {
        $reason = self::reason($subject, $body);

        if ($reason !== null) {
            throw new InvalidCommitMessageException($reason);
        }
    }
}
