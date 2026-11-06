<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\CorrelationIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Shared\CorrelationId;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;

final class IdentifierTest extends Unit
{
    public const string VALUE = '018f3a1c-6f0e-7c4a-9f2b-0c1d2e3f4a5b';

    public const string OTHER_VALUE = '018f3a1c-6f0e-7c4a-9f2b-0c1d2e3f4a5c';

    /**
     * Every identifier type, paired with the contract it must satisfy.
     *
     * @return array<string, array{Closure(string): StringIdentifierInterface, class-string}>
     */
    public static function identifierProvider(): array
    {
        return [
            'execution' => [ExecutionId::fromString(...), ExecutionIdInterface::class],
            'activity' => [ActivityId::fromString(...), ActivityIdInterface::class],
            'observation' => [ObservationId::fromString(...), ObservationIdInterface::class],
            'occurrence' => [OccurrenceId::fromString(...), OccurrenceIdInterface::class],
            'snapshot' => [SnapshotId::fromString(...), SnapshotIdInterface::class],
            'correlation' => [CorrelationId::fromString(...), CorrelationIdInterface::class],
        ];
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     * @param class-string                               $contract
     *
     * @dataProvider identifierProvider
     */
    public function testIdentifierSatisfiesItsContract(Closure $create, string $contract): void
    {
        $identifier = $create(self::VALUE);

        self::assertInstanceOf($contract, $identifier);
        self::assertInstanceOf(StringIdentifierInterface::class, $identifier);
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testStringConversionIsDeterministicAndVerbatim(Closure $create): void
    {
        $identifier = $create(self::VALUE);

        verify($identifier->toString())->equals(self::VALUE);
        verify($identifier->toString())->equals($identifier->toString());
        verify((string) $identifier)->equals(self::VALUE);
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testEqualityIsByValue(Closure $create): void
    {
        $first = $create(self::VALUE);
        $second = $create(self::VALUE);
        $third = $create(self::OTHER_VALUE);

        self::assertNotSame($first, $second);
        self::assertTrue($first->equals($second));
        self::assertTrue($second->equals($first));
        self::assertFalse($first->equals($third));
    }

    public function testEqualityIsTypeAware(): void
    {
        $executionId = ExecutionId::fromString(self::VALUE);
        $activityId = ActivityId::fromString(self::VALUE);

        verify($executionId->toString())->equals($activityId->toString());

        self::assertFalse($executionId->equals($activityId));
        self::assertFalse($activityId->equals($executionId));
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testEmptyValueIsRejected(Closure $create): void
    {
        $this->expectException(InvalidIdentifierException::class);

        $create('');
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testValueExceedingTheMaximumLengthIsRejected(Closure $create): void
    {
        $this->expectException(InvalidIdentifierException::class);

        $create(str_repeat('a', StringIdentifierValidator::MAX_LENGTH + 1));
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testValueAtTheMaximumLengthIsAccepted(Closure $create): void
    {
        $value = str_repeat('a', StringIdentifierValidator::MAX_LENGTH);

        verify($create($value)->toString())->equals($value);
    }

    /**
     * @param Closure(string): StringIdentifierInterface $create
     *
     * @dataProvider identifierProvider
     */
    public function testWhitespaceIsRejectedRatherThanTrimmed(Closure $create): void
    {
        $this->expectException(InvalidIdentifierException::class);

        $create(' ' . self::VALUE);
    }
}
