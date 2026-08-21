<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Contract;

use ItPack\RunIt\Adapter\Psr\Http\HttpExecutionContext;
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
