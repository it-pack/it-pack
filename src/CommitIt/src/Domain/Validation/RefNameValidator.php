<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Validation;

use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;

/**
 * Pure, dependency-free validator for reference names (branches, tags, remotes).
 *
 * The rules follow git's `check_ref_format` reference rules: no whitespace or
 * control characters, no `..`, no component starting with `.` or ending with
 * `.lock`, and structural `/` constraints. Branch, tag, and remote names share
 * these constraints; the {@see RefKind} is recorded in the thrown message so the
 * vocabulary maps cleanly to the contract exception.
 */
final class RefNameValidator
{
    /**
     * Returns a human-readable reason the name is invalid, or null when valid.
     */
    public static function reason(string $name): ?string
    {
        if ($name === '') {
            return 'The reference name must not be empty.';
        }

        if (trim($name) !== $name) {
            return 'The reference name must not have leading or trailing whitespace.';
        }

        if (preg_match('/[\x00-\x1f\x7f\s]/', $name) === 1) {
            return 'The reference name must not contain control characters or whitespace.';
        }

        if (preg_match('/[~\^:\?\*\[\\\\]/', $name) === 1) {
            return 'The reference name contains a forbidden character.';
        }

        if (str_contains($name, '@{')) {
            return 'The reference name must not contain the sequence "@{".';
        }

        if ($name === '@') {
            return 'The reference name must not be "@".';
        }

        if (str_contains($name, '..')) {
            return 'The reference name must not contain "..".';
        }

        if (str_starts_with($name, '/') || str_ends_with($name, '/') || str_contains($name, '//')) {
            return 'The reference name must not start or end with "/" and must not contain "//".';
        }

        foreach (explode('/', $name) as $component) {
            if (str_starts_with($component, '.')) {
                return 'A reference name component must not start with ".".';
            }

            if (str_ends_with($component, '.lock')) {
                return 'A reference name component must not end with ".lock".';
            }
        }

        return null;
    }

    /**
     * Validates the name, throwing a domain exception tagged with the ref kind.
     *
     * @throws InvalidReferenceNameException
     */
    public static function assertValid(string $name, RefKind $kind): void
    {
        $reason = self::reason($name);

        if ($reason !== null) {
            throw new InvalidReferenceNameException(
                ucfirst($kind->value) . ' name is invalid: ' . $reason,
            );
        }
    }
}
