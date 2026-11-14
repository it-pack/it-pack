<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Behavior\CombinatorTrait;
use LogicException;

/**
 * Select combinator: like RaceGroup, but returns the winning task together
 * with its result. Used when the caller needs to know which child completed
 * (e.g. which IO handle became ready).
 *
 * @implements CombinatorInterface<array{task: TaskInterface|null, result: mixed}>
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-select
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class SelectGroup implements CombinatorInterface
{
    use CombinatorTrait;

    /**
     * @return array{task: TaskInterface|null, result: mixed}
     */
    public function await(): array
    {
        if ($this->resolved) {
            throw new LogicException('SelectGroup::await() may only be called once.');
        }

        $this->resolved = true;
        $this->transitionTo(TaskStatus::Running);

        // Check for already-completed children.
        foreach ($this->children as $child) {
            if ($child->status()->isTerminal()) {
                $result = $this->scheduler->result($child);
                $this->cancelRemaining($child);
                $this->transitionTo(TaskStatus::Succeeded);

                return ['task' => $child, 'result' => $result];
            }
        }

        if ($this->children === []) {
            $this->transitionTo(TaskStatus::Succeeded);

            return ['task' => null, 'result' => null];
        }

        // Wait for the first child to complete via awaitAny() when available.
        if (method_exists($this->scheduler, 'awaitAny')) {
            $completedId = $this->scheduler->awaitAny($this->children);
            assert(is_string($completedId));
            $completed = $this->findChild($completedId);

            if ($completed instanceof TaskInterface) {
                $result = $this->scheduler->result($completed);
                $this->cancelRemaining($completed);
                $this->transitionTo(TaskStatus::Succeeded);

                return ['task' => $completed, 'result' => $result];
            }
        }

        // Fallback: sequential await.
        $child = $this->children[0];
        $result = [];
        $result['result'] = $child->status()->isTerminal()
            ? $this->scheduler->result($child)
            : $this->scheduler->await($child);
        $result['task'] = $child;

        $this->cancelRemaining($child);
        $this->transitionTo(TaskStatus::Succeeded);

        return $result;
    }
}
