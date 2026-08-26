<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole\Http;

use ItPack\RunIt\Adapter\Swoole\Contract\RequestContextExtractorInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ServerExecutionMiddlewareInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;

/**
 * Server request middleware that wraps each request in one RunIt execution.
 *
 * For every request the middleware builds a request execution, delegates the work to the
 * supplied handler (which receives the active scope), records the outcome (duration and a
 * handled observation, plus status when the response exposes it), and relies on the runner's
 * finally block to detach the scope and clear all request-local state. Unhandled throwables
 * are recorded by the runner before propagating.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ServerExecutionMiddleware implements ServerExecutionMiddlewareInterface
{
    public function __construct(
        private readonly ExecutionRunnerInterface $runner,
        private readonly ExecutionNameInterface $name,
        private readonly ExecutionKindInterface $kind,
        private readonly ?RequestContextExtractorInterface $contextExtractor = null,
        private readonly ?MonotonicClockInterface $clock = null,
    ) {
    }

    public function handle(mixed $request, callable $handler): mixed
    {
        $context = $this->contextExtractor?->extract($request) ?? ExecutionContext::empty();
        $definition = ExecutionDefinition::create($this->name, $this->kind, null, [], $context);

        return $this->runner->run($definition, function (ExecutionScopeInterface $scope) use (
            $request,
            $handler
        ): mixed {
            $startedAt = $this->clock?->nowSeconds();

            try {
                $response = $handler($request, $scope);
            } finally {
                $this->recordRequest($scope, $startedAt);
            }

            return $response;
        });
    }

    private function recordRequest(ExecutionScopeInterface $scope, ?int $startedAt): void
    {
        if ($startedAt !== null && $this->clock !== null) {
            $elapsed = max(0, $this->clock->nowSeconds() - $startedAt);
            $scope->measurements()->duration('http.request.duration', (float) $elapsed);
        }

        $scope->observations()->record(
            'HTTP request handled',
            null,
            null,
            null,
            'http.request',
        );
    }
}
