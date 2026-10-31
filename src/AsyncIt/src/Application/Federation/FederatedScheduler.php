<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Contract\Port\RemoteSchedulerPortInterface;
use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\AsyncIt\Contract\RemoteTaskInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Domain\Federation\CancellationToken;
use ItPack\AsyncIt\Domain\RemoteTask;
use ItPack\AsyncIt\Domain\TaskId;
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
 * Distributed cancellation degrades gracefully: if the remote scheduler is
 * unreachable, the local consumer can combine the returned promise with a
 * local timeout via PromiseInterface::wait(), and the task is routed to a
 * dead-letter queue on the remote side.
 *
 * @see https://w3id.org/itpack/asyncit#FederatedScheduler
 * @see https://w3id.org/itpack/asyncit#RemoteSchedulerPort
 * @see https://w3id.org/itpack/asyncit#CancellationToken
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final readonly class FederatedScheduler
{
    public function __construct(
        private RemoteSchedulerPortInterface $port,
        private IdentifierGeneratorInterface $identifierGenerator,
        private CancellationScopeFactoryInterface $scopeFactory,
    ) {
    }

    /**
     * Submits a RemoteTask to a remote scheduler via the port.
     *
     * A CancellationScope is created for the submission and serialized into a
     * CancellationToken that is embedded in the task's payload under the
     * 'cancellation_token' key. The token propagates the cancellation scope
     * across the federation boundary so the remote scheduler can observe
     * cancellation.
     *
     * Returns a PromiseInterface that resolves when the remote task completes.
     * Consumers should combine it with a local timeout via wait() to handle
     * unreachable remote schedulers gracefully.
     */
    public function submit(RemoteTaskInterface $task): PromiseInterface
    {
        $scopeId = $this->identifierGenerator->generate();
        $scope = $this->scopeFactory->create();
        $token = CancellationToken::fromCancellationScope($scope, $scopeId);

        $taskWithToken = new RemoteTask(
            $this->toTaskId($task->id()),
            $task->operation(),
            $task->payload() + [
                'cancellation_token_scope_id' => $token->scopeId(),
                'cancellation_token_reason' => $token->reason(),
                'cancellation_token_cancelled' => $token->isCancelled(),
                'cancellation_token_bound_to' => $task->correlationId(),
            ],
            $task->deadline(),
            $task->priority(),
            $task->correlationId(),
        );

        return $this->port->execute($taskWithToken);
    }

    private function toTaskId(TaskIdInterface $id): TaskId
    {
        if ($id instanceof TaskId) {
            return $id;
        }

        return new TaskId((string) $id);
    }
}
