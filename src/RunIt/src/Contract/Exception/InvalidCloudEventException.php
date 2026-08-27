<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

use InvalidArgumentException;

/**
 * Raised when a CloudEvent or one of its fields cannot be accepted.
 *
 * Messages describe the violated rule only. A rejected value is never echoed
 * into the message when it may carry control characters or sensitive data.
 *
 * @api
 *
 * @see https://cloudevents.io/
 */
final class InvalidCloudEventException extends InvalidArgumentException implements InvalidValueExceptionInterface
{
    public static function missingField(string $field): self
    {
        return new self(sprintf('A CloudEvent must contain the required field "%s".', $field));
    }

    public static function emptyField(string $field): self
    {
        return new self(sprintf('CloudEvent field "%s" must not be empty.', $field));
    }

    public static function unsupportedExtensionKey(string $key): self
    {
        return new self(
            sprintf(
                'CloudEvent extension key "%s" is not a valid extension name; '
                . 'it must consist of lower-case letters, digits, and optional hyphens.',
                $key,
            ),
        );
    }

    public static function malformedTime(string $time): self
    {
        return new self(
            sprintf('CloudEvent time "%s" is not a valid RFC 3339 timestamp.', $time),
        );
    }

    public static function invalidSpecVersion(string $version): self
    {
        return new self(sprintf('Unsupported CloudEvents specversion "%s".', $version));
    }

    public static function eventTooLarge(int $bytes, int $maximum): self
    {
        return new self(
            sprintf('Serialized CloudEvent is %d bytes, exceeding the %d byte limit.', $bytes, $maximum),
        );
    }

    public static function unserializableData(): self
    {
        return new self('CloudEvent data could not be serialized to JSON.');
    }

    public static function malformedJson(): self
    {
        return new self('CloudEvent payload is not valid JSON.');
    }
}
