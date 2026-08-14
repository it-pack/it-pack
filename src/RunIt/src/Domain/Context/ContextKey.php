<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\ContextValueValidator;
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
final readonly class ContextKey implements ContextKeyInterface
{
    use StringProviderBehavior;

    private function __construct(
        private readonly string $value,
    ) {
        new ContextValueValidator()->assertValidKey($value);
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
        return $other::class === static::class
            && $other->toString() === $this->toString();
    }
}
