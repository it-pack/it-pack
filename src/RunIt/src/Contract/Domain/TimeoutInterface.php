<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an elapsed timeout of an execution.
 *
 * A timeout records the allowed limit and the measured elapsed duration together with
 * a cause and optional structured labels. Like a cancellation, it is cooperative: the
 * consumer observes the elapsed time and decides to stop.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface TimeoutInterface
{
    public function limit(): DurationInterface;

    public function elapsed(): DurationInterface;

    public function cause(): string;

    public function labels(): AttributeCollectionInterface;

    public function throwableDescription(): ?ThrowableDescriptionInterface;
}
