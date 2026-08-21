<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Domain\StringValueValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;

/**
 * The default RunIt string-value rules.
 *
 * A value is accepted when it:
 *
 *  - is not empty;
 *  - does not exceed {@see self::MAX_LENGTH} bytes;
 *  - consists solely of letters, digits, and the separators `.`, `-`, `_`.
 *
 * Whitespace, control characters, and symbols outside the allowed set are
 * rejected rather than stripped. Names and kinds are exported as log fields,
 * span attributes (subject to allow lists), and CloudEvents `type` values, so a
 * value that cannot inject a delimiter or a line break is safer than one that
 * has been repaired.
 *
 * The rules are structural, not a closed vocabulary: an execution may be named
 * `configuration.resolve` today and `llm.pipeline` tomorrow without a code
 * change. Occurrence types add a stricter format on top of these rules (see
 * {@see OccurrenceType});
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final readonly class StringValueValidator implements StringValueValidatorInterface
{
    /**
     * The maximum accepted string-value length in bytes.
     *
     * Names may carry a dotted path; kinds and types are kept shorter so they
     * stay low-cardinality when used as metric dimensions or event keys.
     */
    public const int MAX_LENGTH = 128;

    private const string ALLOWED = '/^[A-Za-z0-9._-]++\z/';

    public function validate(string $value): void
    {
        self::assertValid($value);
    }

    /**
     * Applies the default rules without requiring an instance.
     *
     * @throws InvalidStringValueException when a rule is violated
     */
    public static function assertValid(string $value): void
    {
        if ($value === '') {
            throw InvalidStringValueException::emptyValue();
        }

        $length = strlen($value);

        if ($length > self::MAX_LENGTH) {
            throw InvalidStringValueException::tooLong($length, self::MAX_LENGTH);
        }

        if (preg_match(self::ALLOWED, $value) !== 1) {
            throw InvalidStringValueException::unsupportedCharacters();
        }
    }
}
