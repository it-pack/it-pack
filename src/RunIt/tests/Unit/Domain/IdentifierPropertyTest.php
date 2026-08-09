<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\CorrelationId;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use Throwable;

/**
 * Randomized checks of the identifier invariants.
 *
 * The generators are seeded, so a failure reproduces exactly. Each property is
 * checked against a fresh sample on every run of the suite only in the sense
 * that the sample is large; the sequence itself is deterministic.
 */
final class IdentifierPropertyTest extends Unit
{
    private const int SEED = 20_260_809;
    private const int SAMPLE_SIZE = 500;

    /**
     * Property: every value built from the accepted alphabet, within the
     * accepted length, is accepted and round-trips verbatim.
     */
    public function testAcceptedValuesRoundTripVerbatim(): void
    {
        mt_srand(self::SEED);

        for ($sample = 0; $sample < self::SAMPLE_SIZE; ++$sample) {
            $value = $this->visibleAsciiValue(mt_rand(1, StringIdentifierValidator::MAX_LENGTH));

            $identifier = ExecutionId::fromString($value);

            self::assertSame(
                $value,
                $identifier->toString(),
                sprintf('Value %s did not round-trip.', bin2hex($value)),
            );
        }
    }

    /**
     * Property: equality is reflexive, symmetric, and value-based for any
     * accepted value.
     */
    public function testEqualityIsReflexiveAndSymmetric(): void
    {
        mt_srand(self::SEED + 1);

        for ($sample = 0; $sample < self::SAMPLE_SIZE; ++$sample) {
            $value = $this->visibleAsciiValue(mt_rand(1, 64));

            $first = ExecutionId::fromString($value);
            $second = ExecutionId::fromString($value);

            self::assertTrue($first->equals($first));
            self::assertTrue($first->equals($second));
            self::assertTrue($second->equals($first));
        }
    }

    /**
     * Property: two identifiers of different types never compare equal, no
     * matter which accepted value they share.
     */
    public function testDifferentIdentifierTypesNeverCompareEqual(): void
    {
        mt_srand(self::SEED + 2);

        for ($sample = 0; $sample < self::SAMPLE_SIZE; ++$sample) {
            $value = $this->visibleAsciiValue(mt_rand(1, 64));

            $executionId = ExecutionId::fromString($value);
            $activityId = ActivityId::fromString($value);
            $correlationId = CorrelationId::fromString($value);

            self::assertFalse($executionId->equals($activityId));
            self::assertFalse($activityId->equals($correlationId));
            self::assertFalse($correlationId->equals($executionId));
        }
    }

    /**
     * Property: inserting any single byte outside the accepted alphabet into
     * an otherwise valid value always causes rejection.
     */
    public function testAnyForbiddenByteCausesRejection(): void
    {
        mt_srand(self::SEED + 3);

        for ($byte = 0; $byte <= 255; ++$byte) {
            if ($byte >= 0x21 && $byte <= 0x7E) {
                continue;
            }

            $value = $this->visibleAsciiValue(mt_rand(1, 32)) . chr($byte);

            self::assertTrue(
                $this->isRejected(ExecutionId::fromString(...), $value),
                sprintf('Byte 0x%02X was not rejected.', $byte),
            );
        }
    }

    /**
     * Property: any value longer than the documented maximum is rejected,
     * whatever it contains.
     */
    public function testAnyOversizedValueIsRejected(): void
    {
        mt_srand(self::SEED + 4);

        for ($sample = 0; $sample < 100; ++$sample) {
            $length = StringIdentifierValidator::MAX_LENGTH + mt_rand(1, 512);

            self::assertTrue(
                $this->isRejected(
                    ExecutionId::fromString(...),
                    $this->visibleAsciiValue($length),
                ),
                sprintf('A value of %d bytes was not rejected.', $length),
            );
        }
    }

    /**
     * @param callable(string): StringIdentifierInterface $create
     */
    private function isRejected(callable $create, string $value): bool
    {
        try {
            $create($value);
        } catch (InvalidIdentifierException) {
            return true;
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function visibleAsciiValue(int $length): string
    {
        $value = '';

        for ($position = 0; $position < $length; ++$position) {
            $value .= chr(mt_rand(0x21, 0x7E));
        }

        return $value;
    }
}
