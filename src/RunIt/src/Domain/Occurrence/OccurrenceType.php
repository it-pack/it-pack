<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Occurrence;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;

/**
 * The stable, versioned type of an occurrence.
 *
 * Occurrence types follow a stricter format than names and kinds because they
 * become CloudEvents `type` values and must stay low-cardinality and
 * independent of any PHP class name. A valid type is lowercase, dot-separated,
 * and ends with a `v`-prefixed version:
 *
 * ```text
 * itpack.configuration.loaded.v1
 * ```
 *
 * Namespaces are recommended so types from different packages do not collide.
 * Versioning lets a consumer change the payload of an occurrence without
 * breaking existing consumers. The format is validated on construction, so a
 * PHP class name can never be used as an implicit event type.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#occurrenceType
 */
final class OccurrenceType implements OccurrenceTypeInterface
{
    use ValidatedStringValueBehavior;

    public const string FORMAT = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*\.v[0-9]+$/';

    public static function fromString(string $value): static
    {
        $instance = new static($value);

        if (preg_match(self::FORMAT, $value) !== 1) {
            throw InvalidStringValueException::malformedOccurrenceType();
        }

        return $instance;
    }

    public function version(): string
    {
        $position = strrpos($this->toString(), '.v');

        return $position === false ? '' : substr($this->toString(), $position + 2);
    }
}
