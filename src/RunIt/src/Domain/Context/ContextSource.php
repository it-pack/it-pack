<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\StringProviderBehavior;
use ItPack\RunIt\Behavior\Domain\StringValueEquality;
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
final readonly class ContextSource implements ContextSourceInterface
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this source.
     *
     * Declared `readonly` (rather than a virtual hook) so the class can stay a
     * `readonly class`; the value is validated and assigned exactly once, on
     * construction. Callers read `$source->value` or `toString()`.
     */
    public readonly string $value;

    private function __construct(string $value)
    {
        new StringValueValidator()->validate($value);

        $this->value = $value;
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
        return $this->value;
    }

    public function equals(ContextSourceInterface $other): bool
    {
        return StringValueEquality::equals($this, $other);
    }
}
