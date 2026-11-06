<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

/**
 * Failure policy that collects the error and continues.
 *
 * The collector receives the original error and the artifact that failed to publish so
 * a caller can inspect, count, or later replay failures. The policy never throws, so the
 * operation continues as if publication had succeeded.
 *
 * @see https://w3id.org/itpack/runit#PublicationFailurePolicy
 */
final readonly class CollectAndContinuePolicy implements PublicationFailurePolicyInterface
{
    /**
     * @param Closure(Throwable, ExecutionArtifactInterface): void $collector
     */
    public function __construct(
        private Closure $collector,
    ) {
    }

    public function handle(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        ($this->collector)($error, $artifact);
    }
}
