<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Contract for conformance checks of ContextStorageInterface.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ContextStorageVerifierInterface
{
    public function port(): string;

    public function verify(
        ContextStorageInterface $storage,
        ExecutionScopeInterface $scope
    ): ConformanceResultInterface;
}
