<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;

/**
 * The default RunIt identifier rules.
 *
 * A value is accepted when it:
 *
 *  - is not empty;
 *  - does not exceed {@see self::MAX_LENGTH} bytes;
 *  - consists solely of visible ASCII characters (`0x21`–`0x7E`).
 *
 * Whitespace, control characters, and non-ASCII bytes are rejected rather than
 * stripped. An identifier travels through logs, HTTP headers, metric contexts,
 * and event extension attributes, so a value that cannot inject a line break
 * or a delimiter is safer than one that has been repaired.
 *
 * The rules are structural, not syntactic: UUIDs, ULIDs, W3C trace identifiers,
 * and caller-supplied opaque correlation values are all accepted. Consumers
 * requiring a stricter format implement
 * {@see StringIdentifierValidatorInterface} at the boundary where untrusted
 * values enter the system.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final readonly class StringIdentifierValidator implements StringIdentifierValidatorInterface
{
    /**
     * The maximum accepted identifier length in bytes.
     *
     * Chosen to hold every common identifier format — a UUID (36), a ULID
     * (26), a W3C trace identifier (32) — with room for a vendor prefix, while
     * keeping identifiers bounded wherever they are exported.
     */
    public const int MAX_LENGTH = 128;

    /**
     * Anchored with `\z` rather than `$`, because `$` also matches immediately
     * before a trailing newline and would let a line break through.
     */
    private const string VISIBLE_ASCII = '/^[\x21-\x7E]++\z/';

    /**
     * Applies the default rules without requiring an instance.
     *
     * Identifier value objects validate themselves on construction and must
     * not allocate a collaborator to do so.
     *
     * @throws InvalidIdentifierException when a rule is violated
     */
    public static function assertValid(string $value): void
    {
        if ($value === '') {
            throw InvalidIdentifierException::emptyValue();
        }

        $length = strlen($value);

        if ($length > self::MAX_LENGTH) {
            throw InvalidIdentifierException::tooLong($length, self::MAX_LENGTH);
        }

        if (preg_match(self::VISIBLE_ASCII, $value) !== 1) {
            throw InvalidIdentifierException::unsupportedCharacters();
        }
    }

    public function validate(string $value): void
    {
        self::assertValid($value);
    }
}
