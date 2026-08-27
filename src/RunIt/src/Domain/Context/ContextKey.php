<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Behavior\Domain\StringValueEquality;
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
 * @see https://w3id.org/itpack/runit/taxonomy#ContextKeys
 */
final readonly class ContextKey implements ContextKeyInterface
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this key.
     *
     * Declared `readonly` (rather than a virtual hook) so the class can stay a
     * `readonly class`; the value is validated and assigned exactly once, on
     * construction. Callers read `$key->value` or `toString()`.
     */
    public readonly string $value;

    private function __construct(string $value)
    {
        new ContextValueValidator()->assertValidKey($value);

        $this->value = $value;
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
        return $this->value;
    }

    public function equals(StringValueInterface $other): bool
    {
        return StringValueEquality::equals($this, $other);
    }
}
