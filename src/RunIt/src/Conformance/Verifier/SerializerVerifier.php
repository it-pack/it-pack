<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use Throwable;

/**
 * Conformance checks for {@see SerializerInterface}.
 *
 * The contract requires that {@see SerializerInterface::normalize()} returns a
 * recursively-primitive structure (no PHP objects, resources, or closures) so the
 * result can be encoded deterministically, that the same input always normalizes to
 * the same output, and that {@see SerializerInterface::contentType()} reports a
 * non-empty content type.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SerializerVerifier implements VerifierInterface
{
    public function port(): string
    {
        return SerializerInterface::class;
    }

    public function verify(SerializerInterface $serializer, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        try {
            $contentType = $serializer->contentType();
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('contentType.throws', sprintf('contentType() threw: %s', $e->getMessage())));
        }
        if ($contentType === '') {
            $result = $result->add(new ConformanceViolation('contentType.empty', 'contentType() did not return a non-empty string'));
        }

        $samples = [
            'scalar' => 'hello',
            'nested' => ['a' => 1, 'b' => ['c' => true, 'd' => null], 'list' => [1, 2, 3]],
            'payload' => $fixture->snapshot()->payload(),
        ];

        foreach ($samples as $label => $data) {
            $this->checkNormalize($serializer, $label, $data, $result);
        }

        return $result;
    }

    private function checkNormalize(SerializerInterface $serializer, string $label, mixed $data, ConformanceResult $result): void
    {
        try {
            $first = $serializer->normalize($data);
            $second = $serializer->normalize($data);
        } catch (Throwable $e) {
            $result->add(new ConformanceViolation(sprintf('normalize.%s.throws', $label), sprintf('normalize() threw for %s: %s', $label, $e->getMessage())));

            return;
        }

        if (!$this->isRecursivelyPrimitive($first)) {
            $result->add(new ConformanceViolation(sprintf('normalize.%s.not-primitive', $label), 'normalize() returned a structure containing PHP objects, resources, or closures'));
        }

        if (json_encode($first) !== json_encode($second)) {
            $result->add(new ConformanceViolation(sprintf('normalize.%s.not-deterministic', $label), 'normalize() was not deterministic for the same input'));
        }
    }

    private function isRecursivelyPrimitive(mixed $value): bool
    {
        if (is_object($value) || is_resource($value)) {
            return false;
        }

        if (is_array($value) && array_any($value, fn ($item) => !$this->isRecursivelyPrimitive($item))) {
            return false;
        }

        return true;
    }
}
