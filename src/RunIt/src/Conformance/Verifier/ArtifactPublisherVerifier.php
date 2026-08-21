<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Conformance\Result\ViolationSeverity;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use Throwable;

/**
 * Conformance checks for {@see ArtifactPublisherInterface}.
 *
 * The contract guarantees that {@see ArtifactPublisherInterface::publish()} is
 * safe to call repeatedly for the same artifact (idempotent in the sense that a
 * re-publish never throws) and never mutates the artifact it is given. Publishers
 * that also implement {@see FlushablePublisherInterface} or
 * {@see CloseablePublisherInterface} are additionally checked for non-throwing,
 * repeatable flush and close.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ArtifactPublisherVerifier
{
    public function port(): string
    {
        return ArtifactPublisherInterface::class;
    }

    public function verify(ArtifactPublisherInterface $publisher, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $artifacts = [$fixture->safeObservation(), $fixture->occurrence(), $fixture->measurement(), $fixture->snapshot()];

        foreach ($artifacts as $artifact) {
            $before = $artifact->normalize();

            try {
                $publisher->publish($artifact);
                $publisher->publish($artifact);
            } catch (Throwable $e) {
                return $result->add(new ConformanceViolation(
                    'publish.throws',
                    sprintf('publish() threw for %s: %s', $artifact::class, $e->getMessage()),
                ));
            }

            if ($artifact->normalize() !== $before) {
                $result = $result->add(new ConformanceViolation(
                    'publish.mutates-artifact',
                    'publish() mutated the supplied artifact',
                ));
            }
        }

        if ($publisher instanceof FlushablePublisherInterface) {
            try {
                $publisher->flush();
                $publisher->flush();
            } catch (Throwable $e) {
                $result = $result->add(new ConformanceViolation(
                    'flush.throws',
                    sprintf('flush() threw: %s', $e->getMessage()),
                    ViolationSeverity::Warning,
                ));
            }
        }

        if ($publisher instanceof CloseablePublisherInterface) {
            try {
                $publisher->close();
                $publisher->close();
            } catch (Throwable $e) {
                $result = $result->add(new ConformanceViolation(
                    'close.throws',
                    sprintf('close() threw: %s', $e->getMessage()),
                    ViolationSeverity::Warning,
                ));
            }
        }

        return $result;
    }
}
