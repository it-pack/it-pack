<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;

/**
 * An immutable, monotonic artifact sequence within one execution.
 *
 * Sequences number artifacts so they can be ordered deterministically. The value
 * is a non-negative integer; `next()` returns a new sequence and never mutates
 * the receiver.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
final readonly class ArtifactSequence implements ArtifactSequenceInterface
{
    private function __construct(private int $value)
    {
        if ($this->value < 0) {
            throw InvalidStringValueException::negativeValue('artifact sequence');
        }
    }

    public static function initial(): self
    {
        return new self(0);
    }

    public static function of(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function next(): ArtifactSequenceInterface
    {
        return new self($this->value + 1);
    }
}
