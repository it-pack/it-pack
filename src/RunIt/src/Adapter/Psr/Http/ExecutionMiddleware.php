<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Adapter\Psr\Contract\HttpContextExtractorInterface;
use ItPack\RunIt\Adapter\Psr\Contract\ResponseCorrelationEnricherInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * PSR-15 middleware that runs each HTTP request inside a RunIt execution.
 *
 * The middleware derives an execution definition from the request (via the
 * configured {@see HttpContextExtractorInterface}), runs the handler inside that
 * execution through the {@see ExecutionRunnerInterface}, records the response
 * status (and request failure) as observations, enriches the response with
 * correlation headers, and lets the runner restore the prior context afterwards.
 *
 * Because the runner attaches an ambient scope for the duration of the handler,
 * downstream code and the execution-aware logger decorator see the active
 * execution automatically.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ExecutionRunnerInterface              $runner,
        private ?HttpContextExtractorInterface        $extractor = null,
        private ?ResponseCorrelationEnricherInterface $enricher = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = ($this->extractor ?? new Psr7RequestContextExtractor())->extract($request);
        $definition = $this->definition($context->getName(), $context->getKind(), $context->getContext());

        $executionId = null;

        $response = $this->runner->run(
            $definition,
            function (ExecutionScopeInterface $scope) use ($request, $handler, &$executionId): ResponseInterface {
                $executionId = $scope->execution()->id()->toString();

                try {
                    $inner = $handler->handle($request);
                } catch (Throwable $throwable) {
                    $scope->observations()->record(
                        'http.request.failed',
                        ObservationSeverity::Error,
                        AttributeCollection::fromArray(['error' => $throwable->getMessage()]),
                        $throwable,
                    );

                    throw $throwable;
                }

                $this->recordStatus($scope, $inner->getStatusCode());

                return $inner;
            },
        );

        return ($this->enricher ?? new Psr7ResponseCorrelationEnricher())
            ->enrich($response, $executionId ?? 'unknown', $context->getCorrelationId())
        ;
    }

    private function definition(
        ExecutionNameInterface $name,
        ExecutionKindInterface $kind,
        ExecutionContextInterface $context,
    ): ExecutionDefinitionInterface {
        return ExecutionDefinition::create($name, $kind, linkedExecutionIds: [], context: $context);
    }

    private function recordStatus(ExecutionScopeInterface $scope, int $status): void
    {
        if ($status >= 500) {
            $scope->observations()->record(
                'http.response.server_error',
                ObservationSeverity::Error,
                AttributeCollection::fromArray(['status' => $status]),
            );

            return;
        }

        if ($status >= 400) {
            $scope->observations()->record(
                'http.response.client_error',
                ObservationSeverity::Warning,
                AttributeCollection::fromArray(['status' => $status]),
            );
        }
    }
}
