<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Extracts the inputs needed to start a RunIt execution from an incoming PSR-7
 * server request.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface HttpContextExtractorInterface
{
    public function extract(ServerRequestInterface $request): HttpExecutionContext;
}

/**
 * The RunIt execution inputs derived from an incoming HTTP request.
 *
 * @api
 */
final readonly class HttpExecutionContext
{
    /**
     * @param array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes
     */
    public function __construct(
        public ExecutionNameInterface $name,
        public ExecutionKindInterface $kind,
        public ?string $correlationId,
        public ExecutionContextInterface $context,
        public array $attributes = [],
    ) {
    }
}
