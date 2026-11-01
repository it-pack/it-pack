<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Behavior\CombinatorTrait;
use LogicException;

/**
 * Race combinator: waits for the first child task to complete, returns its
 * result, and cancels the remaining children.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class RaceGroup implements CombinatorInterface
{
    use CombinatorTrait;

    public function await(): mixed
    {
        if ($this->resolved) {
            throw new LogicException('RaceGroup::await() may only be called once.');
        }

        $this->resolved = true;
        $this->transitionTo(TaskStatus::Running);

        // Check for already-completed children.
        foreach ($this->children as $child) {
            if ($child->status()->isTerminal()) {
                $result = $this->scheduler->result($child);
                $this->cancelRemaining($child);
                $this->transitionTo(TaskStatus::Succeeded);

                return $result;
            }
        }

        if ($this->children === []) {
            $this->transitionTo(TaskStatus::Succeeded);

            return null;
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

                return $result;
            }
        }

        // Fallback: sequential await.
        $child = $this->children[0];
        $result = $child->status()->isTerminal()
            ? $this->scheduler->result($child)
            : $this->scheduler->await($child);

        $this->cancelRemaining($child);
        $this->transitionTo(TaskStatus::Succeeded);

        return $result;
    }
}
