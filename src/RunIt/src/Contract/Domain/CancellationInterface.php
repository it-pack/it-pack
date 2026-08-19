<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a cooperative cancellation of an execution.
 *
 * A cancellation is a first-class fact: it carries a human-readable cause, optional
 * structured labels, and an optional safe throwable description. Consumers build a
 * cancellation when they decide to stop, so RunIt never has to forcibly interrupt
 * arbitrary PHP code.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CancellationInterface
{
    public function cause(): string;

    public function labels(): AttributeCollectionInterface;

    public function throwableDescription(): ?ThrowableDescriptionInterface;
}
