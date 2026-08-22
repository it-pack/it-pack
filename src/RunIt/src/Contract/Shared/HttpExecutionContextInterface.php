<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Shared;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;

/**
 * Contract for the RunIt execution inputs derived from an incoming HTTP request.
 *
 * It captures the bounded, classified inputs an adapter extracts from a request
 * so consumers (such as PSR-15 middleware) depend on the abstraction rather than
 * the concrete adapter type.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface HttpExecutionContextInterface
{
    public function getName(): ExecutionNameInterface;

    public function getKind(): ExecutionKindInterface;

    public function getCorrelationId(): ?string;

    public function getContext(): ExecutionContextInterface;

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function getAttributes(): array;
}
