<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole\Contract;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;

/**
 * Extracts incoming correlation context from a server request.
 *
 * The middleware is framework-neutral: a consumer supplies an extractor that reads whatever
 * correlation identifiers their transport carries and returns a RunIt execution context.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RequestContextExtractorInterface
{
    public function extract(mixed $request): ExecutionContextInterface;
}
