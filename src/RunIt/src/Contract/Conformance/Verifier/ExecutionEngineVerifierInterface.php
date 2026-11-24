<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;

/**
 * Contract for conformance checks of ExecutionEngineInterface.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionEngineVerifierInterface
{
    public function port(): string;

    public function verify(
        ExecutionEngineInterface $engine,
        ExecutionScopeInterface $scope
    ): ConformanceResultInterface;
}
