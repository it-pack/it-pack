<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use Throwable;

/**
 * Conformance checks for {@see RedactorInterface}.
 *
 * The contract requires that redaction never throws, never mutates the source
 * artifact, and produces a projection in which a known secret no longer appears.
 * A safe artifact must redact to an equal projection so ordinary content is not
 * distorted.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RedactorVerifier implements VerifierInterface
{
    public function port(): string
    {
        return RedactorInterface::class;
    }

    public function verify(RedactorInterface $redactor, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $safe = $fixture->safeObservation();
        $safeBefore = $safe->normalize();
        try {
            $redactedSafe = $redactor->redact($safe);
        } catch (Throwable $throwable) {
            return $result->add(new ConformanceViolation('redact.safe.throws', sprintf(
                'redact() threw for a safe artifact: %s',
                $throwable->getMessage()
            )));
        }

        if ($redactedSafe->normalize() !== $safeBefore) {
            $result = $result->add(new ConformanceViolation('redact.safe.alters', 'redact() changed a safe artifact'));
        }

        if ($safe->normalize() !== $safeBefore) {
            $result = $result->add(new ConformanceViolation(
                'redact.safe.mutates',
                'redact() mutated the source safe artifact'
            ));
        }

        $secret = $fixture->sensitiveObservation();
        $secretBefore = $secret->normalize();
        try {
            $redactedSecret = $redactor->redact($secret);
        } catch (Throwable $throwable) {
            return $result->add(new ConformanceViolation('redact.secret.throws', sprintf(
                'redact() threw for a sensitive artifact: %s',
                $throwable->getMessage()
            )));
        }

        if ($secret->normalize() !== $secretBefore) {
            $result = $result->add(new ConformanceViolation(
                'redact.secret.mutates',
                'redact() mutated the source sensitive artifact'
            ));
        }

        if ($this->contains($redactedSecret->normalize(), $fixture->sensitiveAttributeValue())) {
            $result = $result->add(new ConformanceViolation(
                'redact.secret.leaks',
                'redact() left the sensitive value visible in the redacted artifact'
            ));
        }

        return $result;
    }

    private function contains(mixed $data, string $needle): bool
    {
        if (is_string($data)) {
            return str_contains($data, $needle);
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->contains($value, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
