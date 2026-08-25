<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\CommandExecutionCoordinatorInterface;
use ItPack\CommandIt\Contract\Application\CommandExecutorInterface;
use ItPack\CommandIt\Contract\Application\CommandRegistryInterface;
use ItPack\CommandIt\Contract\Application\HandlerResolverInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Contract\Port\ClockInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;
use ItPack\CommandIt\Domain\CommandContext;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;

/**
 * The application-core command executor.
 *
 * Runs the full lifecycle: validate the command against the registry, begin the
 * execution (emitting `command.received.v1`), resolve the handler lazily, run it
 * inside a fiber-bound context through the middleware chain, complete the
 * execution (emitting `command.handled.v1` or `command.failed.v1`), and return
 * the result. On handler failure the original {@see \Throwable} is rethrown and
 * never replaced by a reporter/storage error.
 *
 * @api
 */
final class CommandExecutor implements CommandExecutorInterface
{
    /**
     * @param list<CommandMiddlewareInterface> $middlewares
     */
    public function __construct(
        private CommandRegistryInterface $registry,
        private HandlerResolverInterface $resolver,
        private CommandExecutionCoordinatorInterface $coordinator,
        private ExecutionContextProviderInterface $context,
        private CommandExecutionStorageInterface $storage,
        private ClockInterface $clock,
        private IdentifierGeneratorInterface $ids,
        /** @var list<CommandMiddlewareInterface> */
        private array $middlewares = [],
        private ?SerializerInterface $serializer = null,
    ) {
    }

    public function dispatch(CommandInterface $command): CommandResultInterface
    {
        $name = $command->name()->toString();

        if (!$this->registry->has($name)) {
            throw HandlerNotFoundException::forCommand($name);
        }

        $definition = $this->registry->definition($name);
        $executionId = CommandExecutionId::fromString($this->ids->generate());

        $startedAt = $this->clock->nowSeconds();
        $monoStart = $this->clock->monotonicNanoseconds();

        $execution = $this->coordinator->begin($definition, $executionId, $startedAt);
        $this->record(fn () => $this->storage->store($executionId, $execution));

        $handler = $this->resolver->resolve($command);
        $chain = new MiddlewareChain($this->middlewares);
        $contextToken = $this->context->enter($this->buildContext($definition->name()->toString(), $executionId->toString()));

        try {
            try {
                $result = $chain->process($command, $handler, $this->context);
            } catch (\Throwable $thrown) {
                try {
                    $durationMs = $this->durationMs($this->clock->monotonicNanoseconds() - $monoStart);
                    $failed = $this->coordinator->fail($execution, $thrown, $this->clock->nowSeconds(), $durationMs);
                    $this->record(fn () => $this->storage->store($executionId, $failed));
                } catch (\Throwable) {
                    // Recording the failure must never replace the business exception.
                }

                throw $thrown;
            }

            $durationMs = $this->durationMs($this->clock->monotonicNanoseconds() - $monoStart);
            $stored = $result->withPayload($this->normalizePayload($result->payload()));
            $completed = $this->coordinator->succeed($execution, $stored, $this->clock->nowSeconds(), $durationMs);
            $this->record(fn () => $this->storage->store($executionId, $completed));

            return $result->withExecution($completed);
        } finally {
            $this->context->leave($contextToken);
        }
    }

    private function buildContext(string $command, string $executionId): CommandContextInterface
    {
        return new CommandContext()
            ->with('command', $command, Classification::Internal)
            ->with('execution_id', $executionId, Classification::Internal)
        ;
    }

    private function durationMs(int $nanoseconds): int
    {
        return intdiv($nanoseconds, 1_000_000);
    }

    /**
     * Best-effort normalization of the result payload before persistence.
     *
     * A serialization failure must never replace the business result, so the
     * original payload is kept when the serializer rejects the data.
     */
    private function normalizePayload(mixed $payload): mixed
    {
        if ($this->serializer === null) {
            return $payload;
        }

        try {
            return $this->serializer->normalize($payload);
        } catch (\Throwable) {
            return $payload;
        }
    }

    /**
     * Best-effort persistence/observation: a storage or reporter failure must
     * never replace the business result or the original business exception.
     */
    private function record(\Closure $operation): void
    {
        try {
            $operation();
        } catch (\Throwable) {
        }
    }
}
