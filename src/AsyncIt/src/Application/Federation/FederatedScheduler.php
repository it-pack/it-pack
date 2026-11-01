<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\DeadLetterAwarePromiseFactoryInterface;
use ItPack\AsyncIt\Contract\Port\DeadLetterQueueInterface;
use ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\AsyncIt\Domain\Federation\CancellationToken;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Schedules RemoteTask submissions across a federation of remote AsyncIt
 * schedulers.
 *
 * Accepts serializable RemoteTask definitions, propagates a CancellationScope
 * across the federation boundary via a serializable CancellationToken, and
 * routes each task to a remote scheduler through a RemoteSchedulerPort.
 * Returns a PromiseInterface whose result resolves when the remote task
 * completes.
 *
 * Distributed cancellation degrades gracefully: an optional local timeout
 * can be passed to submit(); if it elapses before the remote task completes,
 * the task is routed to the dead-letter queue. Correlation IDs are preserved
 * from the task definition for federation-boundary tracing; scope IDs are
 * generated via RunIt's IdentifierGeneratorInterface.
 *
 * @see https://w3id.org/itpack/asyncit#FederatedScheduler
 * @see https://w3id.org/itpack/asyncit#RemoteSchedulerPort
 * @see https://w3id.org/itpack/asyncit#CancellationToken
 * @see https://w3id.org/itpack/asyncit#DeadLetterQueue
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final readonly class FederatedScheduler
{
    public function __construct(
        private RemoteSchedulerPortInterface $port,
        private IdentifierGeneratorInterface $identifierGenerator,
        private CancellationScopeFactoryInterface $scopeFactory,
        private DeadLetterQueueInterface $deadLetterQueue,
        private DeadLetterAwarePromiseFactoryInterface $deadLetterAwarePromiseFactory,
    ) {
    }

    /**
     * Submits a RemoteTask to a remote scheduler via the port.
     *
     * A CancellationScope is created for the submission and serialized into a
     * CancellationToken that is embedded in the task's payload under scalar
     * keys. The token propagates the cancellation scope across the federation
     * boundary so the remote scheduler can observe cancellation.
     *
     * When $localTimeout is provided, the returned promise wraps the remote
     * result in a DeadLetterAwarePromise: if the local timeout elapses before
     * the remote task completes, the task is enqueued to the dead-letter
     * queue. Consumers should still combine with PromiseInterface::wait()
     * for unreachable remote schedulers.
     *
     * The task's correlation id is preserved as-is for federation-boundary
     * tracing; {@see \ItPack\AsyncIt\Domain\RemoteTask} normalizes an empty
     * correlation id to the task id.
     *
     * @param RemoteTaskInterface $task The task definition to submit remotely.
     * @param DurationInterface|null $localTimeout Optional monotonic deadline
     *        after which the task is dead-lettered locally.
     * @return PromiseInterface Resolves when the remote task completes.
     */
    public function submit(RemoteTaskInterface $task, ?DurationInterface $localTimeout = null): PromiseInterface
    {
        $scopeId = $this->identifierGenerator->generate();
        $scope = $this->scopeFactory->create();
        $token = CancellationToken::fromCancellationScope($scope, $scopeId);

        $taskWithToken = new RemoteTask(
            $task->id(),
            $task->operation(),
            [
                'cancellation_token_scope_id' => $token->scopeId(),
                'cancellation_token_reason' => $token->reason(),
                'cancellation_token_cancelled' => $token->isCancelled(),
                'cancellation_token_bound_to' => $task->correlationId(),
            ] + $task->payload(),
            $task->deadline(),
            $task->priority(),
            $task->correlationId(),
        );

        $promise = $this->port->execute($taskWithToken);

        if ($localTimeout instanceof DurationInterface) {
            return $this->deadLetterAwarePromiseFactory->create(
                $promise,
                $taskWithToken,
                $this->deadLetterQueue,
            );
        }

        return $promise;
    }
}
