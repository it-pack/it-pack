<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use Throwable;

/**
 * Conformance checks for {@see IdentifierGeneratorInterface}.
 *
 * Every generated value must satisfy the canonical
 * {@see \ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface} rules
 * (non-empty, bounded, printable ASCII) and a run of calls must not repeat a
 * value, so the generator is safe to use for execution identity on a hot path.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final class IdentifierGeneratorVerifier implements VerifierInterface
{
    private const int SAMPLE_SIZE = 200;

    public function port(): string
    {
        return IdentifierGeneratorInterface::class;
    }

    public function verify(IdentifierGeneratorInterface $generator, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());
        $validator = $fixture->stringIdentifierValidator();
        $seen = [];

        for ($i = 0; $i < self::SAMPLE_SIZE; $i++) {
            try {
                $value = $generator->generate();
            } catch (Throwable $e) {
                return $result->add(new ConformanceViolation('generate.throws', sprintf('generate() threw: %s', $e->getMessage())));
            }

            if ($value === '') {
                $result = $result->add(new ConformanceViolation('generate.empty', 'generate() returned an empty or non-string value'));

                continue;
            }

            try {
                $validator->validate($value);
            } catch (Throwable $e) {
                $result = $result->add(new ConformanceViolation('generate.invalid-format', sprintf('generated value "%s" violates identifier rules: %s', $value, $e->getMessage())));
            }

            if (isset($seen[$value])) {
                $result = $result->add(new ConformanceViolation('generate.not-unique', sprintf('generate() returned the duplicate value "%s"', $value)));
            }

            $seen[$value] = true;
        }

        return $result;
    }
}
