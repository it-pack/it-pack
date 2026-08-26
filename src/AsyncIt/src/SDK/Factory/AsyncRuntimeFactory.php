<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Factory;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

/**
 * Assembles the reference {@see AsyncRuntime} from RunIt first-party adapters.
 *
 * It swaps the reference stand-ins for real RunIt collaborators: the system
 * {@see SystemMonotonicClock}, a {@see FailSafeArtifactPublisher} (recursion
 * guard + {@see CallbackHealthReporter} health sink) wrapping an
 * {@see InMemoryArtifactPublisher}, and the LIFO {@see MemoryContextStorage}
 * (validated by RunIt's context-storage verifier). The descriptor — never a
 * runtime name — drives the negotiated {@see EngineCapability}.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/runit#Execution
 */
final class AsyncRuntimeFactory
{
    /**
     * Assembles the reference runtime with RunIt first-party adapters.
     */
    public static function create(): AsyncRuntime
    {
        $clock = new SystemMonotonicClock();
        $storage = new MemoryContextStorage();
        $poller = new PollerFactory()->create();
        $idGenerator = new RandomIdentifierGenerator();
        $taskFactory = new TaskFactory($idGenerator);
        $fiberFactory = new FiberFactory();
        $cancellationScopeFactory = new CancellationScopeFactory();

        $failures = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$failures): void {
                $failures[] = $error;
            },
        );
        $publisher = new FailSafeArtifactPublisher(
            new InMemoryArtifactPublisher(),
            new FailOpenPolicy(),
            $health,
        );

        $schedulerFactory = new SchedulerFactory(
            $taskFactory,
            $fiberFactory,
            $poller,
            $clock,
            $storage,
            $cancellationScopeFactory,
            $publisher,
        );

        $descriptor = new CapabilityDescriptor([
            CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
            CapabilityKind::NativeChannels->value => CapabilityStatus::Unavailable,
            CapabilityKind::BlockingHook->value => CapabilityStatus::Unavailable,
        ]);

        $capability = new EngineCapability(
            $descriptor->statusFor(CapabilityKind::FiberExecution) === CapabilityStatus::Supported,
            false,
            false,
        );

        return new AsyncRuntime(
            $schedulerFactory,
            $taskFactory,
            $cancellationScopeFactory,
            $idGenerator,
            $capability,
        );
    }
}
