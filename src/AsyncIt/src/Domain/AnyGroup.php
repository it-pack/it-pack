<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\Behavior\CombinatorTrait;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Any combinator: waits for the first child task to SUCCEED, returns its
 * result, and ignores failures of other children. If all children fail, throws
 * the first failure.
 *
 * @implements CombinatorInterface<mixed>
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-any
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class AnyGroup implements CombinatorInterface
{
    use CombinatorTrait;

    private ?Throwable $firstFailure = null;

    public function await(): mixed
    {
        if ($this->resolved) {
            throw new LogicException('AnyGroup::await() may only be called once.');
        }

        $this->resolved = true;
        $this->transitionTo(TaskStatus::Running);

        foreach ($this->children as $child) {
            try {
                $result = $this->scheduler->await($child);

                $this->transitionTo(TaskStatus::Succeeded);

                return $result;
            } catch (Throwable $e) {
                $this->firstFailure ??= $e;
            }
        }

        // All children failed.
        $this->transitionTo(TaskStatus::Failed);

        throw $this->firstFailure ?? new RuntimeException('AnyGroup: all children failed.');
    }
}
