<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;

/**
 * An immutable point in time, expressed as Unix epoch seconds.
 *
 * An instant has no timezone: it is a single canonical value, and any local
 * representation is derived at the edge (for example, in a serializer or an
 * adapter). Durations are never expressed as instants; they use monotonic time.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 */
final readonly class Instant implements InstantInterface
{
    private function __construct(private int $epochSeconds)
    {
        if ($this->epochSeconds < 0) {
            throw InvalidStringValueException::negativeValue('instant epoch seconds');
        }
    }

    /**
     * Creates an instant from Unix epoch seconds.
     *
     * A negative value is rejected by the constructor guard.
     */
    public static function of(int $epochSeconds): self
    {
        return new self($epochSeconds);
    }

    /**
     * Creates an instant for the current wall-clock time.
     */
    public static function now(): self
    {
        return new self(time());
    }

    public function epochSeconds(): int
    {
        return $this->epochSeconds;
    }

    public function toString(): string
    {
        return (string) $this->epochSeconds;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
