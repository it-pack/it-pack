<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use Throwable;

/**
 * Conformance checks for {@see SamplingPolicyInterface}.
 *
 * The contract requires that {@see SamplingPolicyInterface::sample()} returns a
 * boolean for any execution artifact, never throws, is deterministic for the same
 * artifact, and never mutates the artifact it evaluates.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SamplingPolicyVerifier implements VerifierInterface
{
    public function port(): string
    {
        return SamplingPolicyInterface::class;
    }

    public function verify(SamplingPolicyInterface $policy, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        foreach ($fixture->samplingArtifacts() as $artifact) {
            $before = $artifact->normalize();

            try {
                $first = $policy->sample($artifact);
                $second = $policy->sample($artifact);
            } catch (Throwable $e) {
                return $result->add(new ConformanceViolation('sample.throws', sprintf(
                    'sample() threw for %s: %s',
                    $artifact::class,
                    $e->getMessage()
                )));
            }

            if ($first !== $second) {
                $result = $result->add(new ConformanceViolation('sample.not-deterministic', sprintf(
                    'sample() was not deterministic for %s',
                    $artifact::class
                )));
            }

            if ($artifact->normalize() !== $before) {
                $result = $result->add(new ConformanceViolation('sample.mutates', sprintf(
                    'sample() mutated the artifact %s',
                    $artifact::class
                )));
            }
        }

        return $result;
    }
}
