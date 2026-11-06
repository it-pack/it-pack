<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use RuntimeException;

/**
 * Lifecycle coordinator decorator whose terminal transition throws while the
 * remaining coordination delegates, for exercising transition-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class TransitionFailingCoordinator implements ExecutionLifecycleCoordinatorInterface
{
    public function __construct(private ExecutionLifecycleCoordinatorInterface $inner)
    {
    }

    public function transition(
        ExecutionInterface $execution,
        ExecutionResultInterface $result,
        InstantInterface $endedAt,
    ): ExecutionInterface {
        throw new RuntimeException('transition is broken');
    }

    public function start(ExecutionInterface $execution): ExecutionInterface
    {
        return $this->inner->start($execution);
    }
}
