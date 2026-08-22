<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface;

/**
 * The RunIt execution inputs derived from an incoming HTTP request.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class HttpExecutionContext implements HttpExecutionContextInterface
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

    public function getName(): ExecutionNameInterface
    {
        return $this->name;
    }

    public function getKind(): ExecutionKindInterface
    {
        return $this->kind;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getContext(): ExecutionContextInterface
    {
        return $this->context;
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
