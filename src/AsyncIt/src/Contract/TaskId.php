<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use InvalidArgumentException;

/**
 * Opaque identifier of a single scheduled task within an execution.
 *
 * A task id is unique to one task and never changes. It is a validated string
 * value (non-empty, bounded) so it can be carried through logs and artifacts
 * without leaking structure. The property hook exposes the validated value.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#executionId
 */
final class TaskId
{
    public string $value {
        get => $this->id;
    }

    private string $id;

    public function __construct(string $id)
    {
        if ($id === '') {
            throw new InvalidArgumentException('TaskId must not be empty.');
        }

        $this->id = $id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
