<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\GuardedArtifactPublisher;
use ItPack\RunIt\Application\Publication\PublicationGuard;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use Throwable;

/**
 * @group application
 * @group publication
 */
final class GuardedPublisherTest extends Unit
{
    private function artifact(): ExecutionArtifactInterface
    {
        return Observation::info(ObservationId::fromString('obs-1'), ExecutionId::fromString('exec-1'), 'hello');
    }

    /**
     * Two guarded publishers that share one guard: publishing through the outer one re-enters
     * the same path when the inner (the exporter) tries to publish again.
     */
    private function sharedGuardChain(
        PublicationGuard $guard,
        InMemoryArtifactPublisher $realSink,
        ?CallbackHealthReporter $health
    ): GuardedArtifactPublisher {
        $inner = new GuardedArtifactPublisher($guard, $realSink, $health);

        return new GuardedArtifactPublisher($guard, $inner);
    }

    public function testGuardPreventsDirectRecursion(): void
    {
        $realSink = new InMemoryArtifactPublisher();
        $healthReports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$healthReports): void {
                $healthReports[] = $artifact;
            }
        );

        $guard = new PublicationGuard();
        $guarded = $this->sharedGuardChain($guard, $realSink, $health);

        $guarded->publish($this->artifact());

        self::assertCount(0, $realSink->published(), 'The recursive re-entry must not reach the real sink.');
        self::assertCount(1, $healthReports, 'The recursive publish must be routed to the health reporter.');
    }

    public function testGuardLeavesScopeAfterSuccess(): void
    {
        $guard = new PublicationGuard();
        $sink = new InMemoryArtifactPublisher();
        $guarded = new GuardedArtifactPublisher($guard, $sink);

        self::assertFalse($guard->isActive());
        $guarded->publish($this->artifact());
        self::assertFalse($guard->isActive(), 'The guard must be left even after a successful publish.');
    }

    public function testExporterSelfObservationIsSuppressed(): void
    {
        /** @var GuardedArtifactPublisher|null $guarded */
        $guarded = null;
        $recorded = [];
        $guard = new PublicationGuard();

        // The exporter sink records the artifact it receives and, as a side effect, publishes an
        // observation about its own export through the same guarded pipeline. That self-observation
        // must be suppressed, but the original artifact still reaches the sink.
        $exporter = new CallbackArtifactPublisher(
            static function (ExecutionArtifactInterface $artifact) use (&$recorded, &$guarded): void {
                $recorded[] = $artifact;
                if ($guarded instanceof GuardedArtifactPublisher) {
                    $guarded->publish(Observation::info(
                        ObservationId::fromString('self'),
                        ExecutionId::fromString('exec-1'),
                        'exported'
                    ));
                }
            }
        );

        $guarded = new GuardedArtifactPublisher($guard, $exporter);

        $guarded->publish($this->artifact());

        self::assertCount(1, $recorded, 'The original artifact reaches the sink; the self-observation is suppressed.');
    }
}
