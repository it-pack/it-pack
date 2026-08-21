<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;

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
