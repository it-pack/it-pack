<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Behavior\Domain\StringValueValidator;
use ItPack\RunIt\Contract\Domain\ContextSourceInterface;

/**
 * An optional context entry source identifier.
 *
 * A source records where a context value originated (for example a header name
 * or a configuration key). It is a validated string value and never carries the
 * value itself.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class ContextSource implements ContextSourceInterface
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this source.
     *
     * Backed by {@see ContextSource::$raw}; exposed as a virtual property so
     * callers can read `$source->value` as well as `toString()`.
     */
    public string $value {
        get => $this->raw;
    }

    private function __construct(
        private readonly string $raw,
    ) {
        new StringValueValidator()->validate($raw);
    }

    /**
     * Creates a source from an already known string.
     *
     * @throws \ItPack\RunIt\Contract\Exception\InvalidStringValueException when the
     *                                                                     value is empty, too long, or uses unsupported characters
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->raw;
    }

    public function equals(ContextSourceInterface $other): bool
    {
        return $other->toString() === $this->toString();
    }
}
