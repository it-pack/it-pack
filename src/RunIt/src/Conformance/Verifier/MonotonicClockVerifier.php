<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use Throwable;

/**
 * Conformance checks for {@see MonotonicClockInterface}.
 *
 * Wall-clock and monotonic readings must both be integers and the monotonic
 * reading must never go backwards across samples. {@see MonotonicClockInterface::measure()}
 * must produce a duration whose magnitude is zero for an identical pair and
 * non-negative for an increasing pair.
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
final class MonotonicClockVerifier implements VerifierInterface
{
    private const int SAMPLES = 16;

    public function port(): string
    {
        return MonotonicClockInterface::class;
    }

    public function verify(MonotonicClockInterface $clock): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        try {
            $wall = $clock->nowSeconds();
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('nowSeconds.throws', sprintf(
                'nowSeconds() threw: %s',
                $e->getMessage()
            )));
        }

        $previous = null;
        for ($i = 0; $i < self::SAMPLES; $i++) {
            try {
                $reading = $clock->monotonicNanoseconds();
            } catch (Throwable $e) {
                return $result->add(new ConformanceViolation('monotonicNanoseconds.throws', sprintf(
                    'monotonicNanoseconds() threw: %s',
                    $e->getMessage()
                )));
            }
            if ($previous !== null && $reading < $previous) {
                $result = $result->add(new ConformanceViolation(
                    'monotonicNanoseconds.not-monotonic',
                    'monotonicNanoseconds() decreased between readings'
                ));
                break;
            }
            $previous = $reading;
        }

        try {
            $zero = $clock->measure(100, 100)->nanoseconds();
            $forward = $clock->measure(100, 250)->nanoseconds();
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('measure.throws', sprintf(
                'measure() threw: %s',
                $e->getMessage()
            )));
        }
        if ($zero !== 0) {
            $result = $result->add(new ConformanceViolation(
                'measure.zero',
                'measure(a, a) did not produce a zero duration'
            ));
        }
        if ($forward < 0) {
            $result = $result->add(new ConformanceViolation(
                'measure.negative',
                'measure(lower, higher) produced a negative duration'
            ));
        }

        return $result;
    }
}
