<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;

/**
 * Contract for conformance checks of RedactorInterface.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RedactorVerifierInterface
{
    public function port(): string;

    public function verify(RedactorInterface $redactor, PortFixtureInterface $fixture): ConformanceResultInterface;
}
