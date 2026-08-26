<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Factory;

use ItPack\AsyncIt\Adapter\Revolt\RevoltChannel;
use ItPack\AsyncIt\Adapter\Revolt\RevoltFiberContextStorage;
use ItPack\AsyncIt\Adapter\Revolt\RevoltPoller;
use ItPack\AsyncIt\Adapter\Swoole\SwooleChannel;
use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Adapter\Swow\SwowChannel;
use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowPoller;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
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
 * Assembles the {@see AsyncRuntime} from RunIt first-party adapters and the
 * negotiated native runtime adapter.
 *
 * The active runtime is chosen from a {@see CapabilityDescriptor} built by
 * probing runtime APIs (never `extension_loaded(...)`): Swoole/Swow/Revolt are
 * selected when their coroutine + (where relevant) channel APIs are present,
 * otherwise the `SelectPoller`-backed reference assembly is used. The scheduler,
 * ports, and public contracts are identical across every selection — only the
 * poller, context-storage, and channel backends change.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/runit#Execution
 */
final class AsyncRuntimeFactory
{
    /**
     * Assembles the runtime with the negotiated adapter (Swoole/Swow/Revolt/Reference).
     */
    public static function create(): AsyncRuntime
    {
        [$kind, $descriptor] = (new self())->negotiate();

        $clock = new SystemMonotonicClock();
        $poller = (new self())->buildPoller($kind);
        $storage = (new self())->buildStorage($kind);
        $channel = (new self())->buildChannel($kind);
        $telemetry = (new self())->buildTelemetry();
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
            $channel,
            $telemetry,
        );
    }

    /**
     * @return array{0: RuntimeAdapterKind, 1: CapabilityDescriptor}
     */
    private function negotiate(): array
    {
        if (class_exists('Swoole\\Coroutine') && class_exists('Swoole\\Coroutine\\Channel')) {
            return [
                RuntimeAdapterKind::Swoole,
                new CapabilityDescriptor([
                    CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
                    CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Supported,
                    CapabilityKind::NativeChannels->value => CapabilityStatus::Supported,
                    CapabilityKind::BlockingHook->value => CapabilityStatus::AdapterOnly,
                ]),
            ];
        }

        if (class_exists('Swow\\Coroutine') && class_exists('Swow\\Coroutine\\Channel')) {
            return [
                RuntimeAdapterKind::Swow,
                new CapabilityDescriptor([
                    CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
                    CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Supported,
                    CapabilityKind::NativeChannels->value => CapabilityStatus::Supported,
                    CapabilityKind::BlockingHook->value => CapabilityStatus::AdapterOnly,
                ]),
            ];
        }

        if (class_exists('Revolt\\EventLoop')) {
            return [
                RuntimeAdapterKind::Revolt,
                new CapabilityDescriptor([
                    CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
                    CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Supported,
                    CapabilityKind::NativeChannels->value => CapabilityStatus::Unavailable,
                    CapabilityKind::BlockingHook->value => CapabilityStatus::AdapterOnly,
                ]),
            ];
        }

        return [
            RuntimeAdapterKind::Reference,
            new CapabilityDescriptor([
                CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
                CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
                CapabilityKind::NativeChannels->value => CapabilityStatus::Unavailable,
                CapabilityKind::BlockingHook->value => CapabilityStatus::Unavailable,
            ]),
        ];
    }

    private function buildPoller(RuntimeAdapterKind $kind): \ItPack\AsyncIt\Contract\PollerPortInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwoolePoller(),
            RuntimeAdapterKind::Swow => new SwowPoller(),
            RuntimeAdapterKind::Revolt => new RevoltPoller(),
            RuntimeAdapterKind::Reference => (new PollerFactory())->create(),
        };
    }

    private function buildStorage(RuntimeAdapterKind $kind): \ItPack\RunIt\Contract\Port\ContextStorageInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwooleCoroutineContextStorage(),
            RuntimeAdapterKind::Swow => new SwowCoroutineContextStorage(),
            RuntimeAdapterKind::Revolt => new RevoltFiberContextStorage(),
            RuntimeAdapterKind::Reference => new MemoryContextStorage(),
        };
    }

    private function buildChannel(RuntimeAdapterKind $kind): TaskChannelInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwooleChannel(new \Swoole\Coroutine\Channel()),
            RuntimeAdapterKind::Swow => new SwowChannel(new \Swow\Coroutine\Channel()),
            RuntimeAdapterKind::Revolt => new RevoltChannel(new EmulatedChannel()),
            RuntimeAdapterKind::Reference => new EmulatedChannel(),
        };
    }

    private function buildTelemetry(): TelemetryBridgeInterface
    {
        return new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );
    }
}
