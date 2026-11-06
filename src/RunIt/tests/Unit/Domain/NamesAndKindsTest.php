<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\StringValueValidator;
use ItPack\RunIt\Contract\Domain\ActivityKindInterface;
use ItPack\RunIt\Contract\Domain\ActivityNameInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Contract\Domain\StringValueInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

final class NamesAndKindsTest extends Unit
{
    public const string VALUE = 'configuration.resolve';

    public const string OTHER_VALUE = 'cli.command';

    /**
     * Every name/kind type, paired with the contract it must satisfy.
     *
     * @return array<string, array{Closure(string): StringValueInterface, class-string}>
     */
    public static function valueProvider(): array
    {
        return [
            'execution name' => [ExecutionName::fromString(...), ExecutionNameInterface::class],
            'execution kind' => [ExecutionKind::fromString(...), ExecutionKindInterface::class],
            'activity name' => [ActivityName::fromString(...), ActivityNameInterface::class],
            'activity kind' => [ActivityKind::fromString(...), ActivityKindInterface::class],
            'measurement name' => [MeasurementName::fromString(...), MeasurementNameInterface::class],
            'snapshot type' => [SnapshotType::fromString(...), SnapshotTypeInterface::class],
        ];
    }

    /**
     * @param Closure(string): StringValueInterface $create
     * @param class-string                               $contract
     *
     * @dataProvider valueProvider
     */
    public function testValueSatisfiesItsContract(callable $create, string $contract): void
    {
        $value = $create(self::VALUE);

        self::assertInstanceOf($contract, $value);
        self::assertInstanceOf(StringValueInterface::class, $value);
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testStringConversionIsDeterministicAndVerbatim(callable $create): void
    {
        $value = $create(self::VALUE);

        verify($value->toString())->equals(self::VALUE);
        verify($value->toString())->equals($value->toString());
        verify((string) $value)->equals(self::VALUE);
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testEqualityIsByValue(callable $create): void
    {
        $first = $create(self::VALUE);
        $second = $create(self::VALUE);
        $third = $create(self::OTHER_VALUE);

        self::assertNotSame($first, $second);
        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($third));
    }

    public function testEqualityIsTypeAware(): void
    {
        $executionName = ExecutionName::fromString(self::VALUE);
        $activityName = ActivityName::fromString(self::VALUE);

        verify($executionName->toString())->equals($activityName->toString());

        self::assertFalse($executionName->equals($activityName));
        self::assertFalse($activityName->equals($executionName));
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testEmptyValueIsRejected(callable $create): void
    {
        $this->expectException(InvalidStringValueException::class);

        $create('');
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testValueExceedingTheMaximumLengthIsRejected(callable $create): void
    {
        $this->expectException(InvalidStringValueException::class);

        $create(str_repeat('a', StringValueValidator::MAX_LENGTH + 1));
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testValueAtTheMaximumLengthIsAccepted(callable $create): void
    {
        $value = str_repeat('a', StringValueValidator::MAX_LENGTH);

        verify($create($value)->toString())->equals($value);
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testSymbolsOutsideTheAllowedSetAreRejected(callable $create): void
    {
        $this->expectException(InvalidStringValueException::class);

        $create('bad name!');
    }

    /**
     * @param Closure(string): StringValueInterface $create
     *
     * @dataProvider valueProvider
     */
    public function testACommonSeparatorSetIsAccepted(callable $create): void
    {
        self::assertSame('http.request', $create('http.request')->toString());
        self::assertSame('cli_command', $create('cli_command')->toString());
        self::assertSame('queue-job', $create('queue-job')->toString());
    }

    public function testKindsRemainExtensible(): void
    {
        $custom = ExecutionKind::fromString('llm.pipeline');

        self::assertSame('llm.pipeline', $custom->toString());
        self::assertFalse(ExecutionKind::fromString('http.request')->equals($custom));
    }
}
