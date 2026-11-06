<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Behavior\Domain\StringValueEquality;
use ItPack\RunIt\Contract\Domain\AttributeKeyInterface;
use ItPack\RunIt\Contract\Domain\StringValueInterface;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;

/**
 * A validated attribute key.
 *
 * Keys follow the attribute-key rules: non-empty, at most 256 bytes, and made
 * of letters, digits, and the separators `.`, `-`, `_` (so dotted namespacing
 * such as `http.status` is allowed). These rules are identical to those
 * enforced by {@see AttributeCollection}, so a key validated here is always
 * accepted by the collection and the two cannot drift apart.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class AttributeKey implements AttributeKeyInterface
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this key.
     *
     * Declared `readonly` (rather than a virtual hook) so the class can stay a
     * `readonly class`; the value is validated and assigned exactly once, on
     * construction. Callers read `$key->value` or `toString()`.
     */
    public string $value;

    private function __construct(string $value)
    {
        new AttributeValidator()->assertValidKey($value);

        $this->value = $value;
    }

    /**
     * Creates a key from an already known string.
     *
     * @throws InvalidAttributeException when the
     *                                                                     key is empty, too long, or uses unsupported characters
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(StringValueInterface $other): bool
    {
        return StringValueEquality::equals($this, $other);
    }
}
