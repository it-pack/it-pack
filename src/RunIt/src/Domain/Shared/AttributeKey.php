<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Contract\Domain\AttributeKeyInterface;
use ItPack\RunIt\Contract\Domain\StringValueInterface;

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

    private function __construct(
        private readonly string $value,
    ) {
        new AttributeValidator()->assertValidKey($value);
    }

    /**
     * Creates a key from an already known string.
     *
     * @throws \ItPack\RunIt\Contract\Exception\InvalidAttributeException when the
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
        return $other::class === static::class
            && $other->toString() === $this->toString();
    }
}
