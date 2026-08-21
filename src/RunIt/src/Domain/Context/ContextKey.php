<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Contract\Domain\ContextKeyInterface;
use ItPack\RunIt\Contract\Domain\StringValueInterface;

/**
 * A validated context key.
 *
 * Keys follow the context-key rules: non-empty, at most 256 bytes, and made of
 * letters, digits, and the separators `.`, `-`, `_` (so dotted namespacing such
 * as `tenant.id` is allowed). These match the rules enforced by
 * {@see ExecutionContext}, so a key validated here is always accepted there.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class ContextKey implements ContextKeyInterface
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this key.
     *
     * Backed by {@see ContextKey::$raw}; exposed as a virtual property so
     * callers can read `$key->value` as well as `toString()`.
     */
    public string $value {
        get => $this->raw;
    }

    private function __construct(
        private readonly string $raw,
    ) {
        new ContextValueValidator()->assertValidKey($raw);
    }

    /**
     * Creates a key from an already known string.
     *
     * @throws \ItPack\RunIt\Contract\Exception\InvalidContextValueException when the
     *                                                                     key is empty, too long, or uses unsupported characters
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->raw;
    }

    public function equals(StringValueInterface $other): bool
    {
        return $other::class === static::class
            && $other->toString() === $this->toString();
    }
}
