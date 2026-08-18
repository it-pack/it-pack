<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\CancellationInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\AttributeCollection;

/**
 * A cooperative cancellation of an execution.
 *
 * A cancellation is built by a consumer that decides to stop, not by RunIt interrupting
 * arbitrary PHP code. It preserves the consumer-defined cause verbatim so the resulting
 * failed execution can explain why it stopped.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class Cancellation implements CancellationInterface
{
    private function __construct(
        private string $cause,
        private AttributeCollectionInterface $labels,
        private ?ThrowableDescriptionInterface $description,
    ) {
    }

    public static function of(
        string $cause,
        ?AttributeCollectionInterface $labels = null,
        ?ThrowableDescriptionInterface $description = null,
    ): self {
        return new self($cause, $labels ?? AttributeCollection::empty(), $description);
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
