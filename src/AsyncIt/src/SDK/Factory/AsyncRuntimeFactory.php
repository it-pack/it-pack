<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Factory;

use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler;
use ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssemblerInterface;
use ItPack\AsyncIt\Application\Factory\SchedulerFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
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
use Revolt\EventLoop;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
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
final class AsyncRuntimeFactory implements RuntimeAssemblerInterface
{
    /**
     * Assembles the runtime with the negotiated adapter (Swoole/Swow/Revolt/Reference).
     *
     * @param ?RuntimeAdapterAssemblerInterface $assembler injected assembler,
     *        defaults to {@see RuntimeAdapterAssembler} when null
     */
    public static function create(
        ?RuntimeAdapterAssemblerInterface $assembler = null,
    ): AsyncRuntime {
        [$kind, $descriptor] = new self()->negotiate();

        $assembler ??= new RuntimeAdapterAssembler();
        $assembly = $assembler->assemble($kind, $descriptor);
        $poller = $assembly->poller;
        $storage = $assembly->storage;
        $channel = $assembly->channel;
        $telemetry = new self()->buildTelemetry();
        $idGenerator = new RandomIdentifierGenerator();
        $taskFactory = new TaskFactory($idGenerator);
        $fiberFactory = new FiberFactory();
        $cancellationScopeFactory = new CancellationScopeFactory();
        $taskGroupFactory = new TaskGroupFactory($cancellationScopeFactory);

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
            $fiberFactory,
            new TaskRecordFactory(),
            $taskFactory,
            $cancellationScopeFactory,
            new SystemMonotonicClock(),
            $poller,
            $storage,
            $publisher,
        );

        $capability = new EngineCapability(
            $descriptor->statusFor(CapabilityKind::FiberExecution) === CapabilityStatus::Supported,
            $descriptor->statusFor(CapabilityKind::BlockingHook) !== CapabilityStatus::Unavailable,
            $descriptor->statusFor(CapabilityKind::FiberExecution) === CapabilityStatus::Supported,
        );

        return new AsyncRuntime(
            $schedulerFactory,
            $taskGroupFactory,
            $idGenerator,
            $capability,
            $channel,
            $telemetry,
        );
    }

    /**
     * Negotiates and returns the active runtime adapter and its capability descriptor.
     *
     * @return array{0: RuntimeAdapterKind, 1: CapabilityDescriptor}
     */
    private function negotiate(): array
    {
        if (class_exists(Coroutine::class) && class_exists(Channel::class)) {
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

        if (class_exists(EventLoop::class)) {
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
                CapabilityKind::EdgeTriggeredPolling->value => PollerFactory::nativePollAvailable()
                    ? CapabilityStatus::Supported
                    : CapabilityStatus::Emulated,
                CapabilityKind::NativeChannels->value => CapabilityStatus::Unavailable,
                CapabilityKind::BlockingHook->value => CapabilityStatus::Unavailable,
            ]),
        ];
    }

    private function buildTelemetry(): TelemetryBridgeInterface
    {
        if (\getenv('OTEL_EXPORTER_OTLP_ENDPOINT') !== false) {
            $bridgeClass = OpenTelemetryTelemetryBridge::class;

            if (class_exists($bridgeClass)) {
                /** @var TelemetryBridgeInterface $bridge */
                $bridge = new $bridgeClass();

                return $bridge;
            }
        }

        return new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );
    }
}
