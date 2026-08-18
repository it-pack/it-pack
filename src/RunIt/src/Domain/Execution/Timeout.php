<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\TimeoutInterface;
use ItPack\RunIt\Domain\Shared\AttributeCollection;

/**
 * An elapsed timeout of an execution.
 *
 * A timeout records the allowed limit alongside the measured elapsed duration. Like a
 * {@see Cancellation}, it is cooperative: the consumer observes the elapsed time and
 * decides to stop, so RunIt never forcibly interrupts the running code.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class Timeout implements TimeoutInterface
{
    private function __construct(
        private DurationInterface $limit,
        private DurationInterface $elapsed,
        private string $cause,
        private AttributeCollectionInterface $labels,
        private ?ThrowableDescriptionInterface $description,
    ) {
    }

    public static function of(
        DurationInterface $limit,
        DurationInterface $elapsed,
        string $cause = 'execution timed out',
        ?AttributeCollectionInterface $labels = null,
        ?ThrowableDescriptionInterface $description = null,
    ): self {
        return new self($limit, $elapsed, $cause, $labels ?? AttributeCollection::empty(), $description);
    }

    public function limit(): DurationInterface
    {
        return $this->limit;
    }

    public function elapsed(): DurationInterface
    {
        return $this->elapsed;
    }

    public function cause(): string
    {
        return $this->cause;
    }

    public function labels(): AttributeCollectionInterface
    {
        return $this->labels;
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return $this->description;
    }
}
