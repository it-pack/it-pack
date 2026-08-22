<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http\Propagation;

use ItPack\RunIt\Adapter\Psr\Contract\HeaderPropagatorInterface;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client decorator that injects RunIt correlation headers into every
 * outbound request.
 *
 * The decorator reads the active execution (and correlation identifier) from the
 * ambient {@see CurrentExecutionInterface} and adds the configured correlation
 * headers through a {@see HeaderPropagatorInterface}. When no execution is active
 * the request passes through unchanged. Requests are immutable, so a modified copy
 * is sent and the original request is never altered.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class Psr18CorrelationClientDecorator implements ClientInterface
{
    public function __construct(
        private ClientInterface           $inner,
        private HeaderPropagatorInterface $propagator,
        private CurrentExecutionInterface $current,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $scope = $this->current->get();

        if ($scope === null) {
            return $this->inner->sendRequest($request);
        }

        $executionId = $scope->execution()->id()->toString();
        $correlationId = $this->correlationId($scope);

        foreach ($this->propagator->headers($executionId, $correlationId) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->inner->sendRequest($request);
    }

    private function correlationId(ExecutionScopeInterface $scope): ?string
    {
        $entry = $scope->context()->find(CorrelationKeys::CONTEXT_CORRELATION_KEY);

        if ($entry === null) {
            return null;
        }

        $value = $entry->value();

        return is_string($value) ? $value : null;
    }
}
