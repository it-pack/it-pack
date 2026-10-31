<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Contract for a serializable task definition destined for execution on a
 * remote AsyncIt scheduler instance.
 *
 * Only task *definitions* are serialized — never suspended Fibers or raw
 * closures. The operation is a fully-qualified job class name plus a payload
 * array, which can be rehydrated into a {@see \ItPack\AsyncIt\Domain\Task} on
 * the remote side. A correlation id propagates across the federation boundary
 * so distributed cancellation and dead-letter handling can trace the work back
 * to its origin.
 *
 * @see https://w3id.org/itpack/asyncit#RemoteTask
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface RemoteTaskInterface extends ValueObjectInterface
{
    public function __toString(): string;

    public function id(): TaskIdInterface;

    /**
     * Returns the fully-qualified job class name to instantiate remotely.
     * Never a raw closure — only serializable class names are permitted.
     */
    public function operation(): string;

    /**
     * Returns the constructor payload for the job class, or an empty array.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;

    /**
     * Returns the optional monotonic deadline after which the task expires.
     */
    public function deadline(): ?DurationInterface;

    /**
     * Returns the task priority (higher = more urgent). Remote schedulers may
     * ignore this value if they do not support prioritised queues.
     */
    public function priority(): int;

    /**
     * Returns the correlation id used to trace this task across federation
     * boundaries. Generated via RunIt's {@see \ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface}.
     */
    public function correlationId(): string;
}
