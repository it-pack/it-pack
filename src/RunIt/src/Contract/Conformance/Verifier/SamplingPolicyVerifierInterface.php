<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;

/**
 * Contract for conformance checks of SamplingPolicyInterface.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface SamplingPolicyVerifierInterface
{
    public function port(): string;

    public function verify(SamplingPolicyInterface $policy, PortFixtureInterface $fixture): ConformanceResultInterface;
}
