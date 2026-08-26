<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;

/**
 * The named pipeline stages that append themselves to a node's processor trail.
 * Stage names double as wire values in serialized provenance, so the string
 * values are part of the contract.
 *
 * @covers \ItPack\ConfigureIt\ValueObject\ProcessorStage
 */
final class ProcessorStageTest extends Unit
{
    /**
     * @dataProvider stageNames
     */
    public function testFromStringResolvesKnownStages(string $value, ProcessorStage $expected): void
    {
        verify(ProcessorStage::fromString($value))->equals($expected);
        verify(ProcessorStage::fromString($value)->value)->equals($value);
    }

    /**
     * @return array<string, array{string, ProcessorStage}>
     */
    public function stageNames(): array
    {
        return [
            'defaults'   => ['DefaultsProcessor', ProcessorStage::Defaults],
            'validation' => ['ValidationProcessor', ProcessorStage::Validation],
            'casting'    => ['CastingProcessor', ProcessorStage::Casting],
            'include'    => ['IncludeProcessor', ProcessorStage::Include],
            'variable'   => ['VariableProcessor', ProcessorStage::Variable],
        ];
    }

    /**
     * Unknown stage names collapse to Defaults rather than throwing, because a
     * generic processor may report an arbitrary class name as its stage.
     *
     * @dataProvider unknownStageNames
     */
    public function testFromStringFallsBackToDefaults(string $value): void
    {
        verify(ProcessorStage::fromString($value))->equals(ProcessorStage::Defaults);
    }

    /**
     * @return array<string, array{string}>
     */
    public function unknownStageNames(): array
    {
        return [
            'empty'                => [''],
            'custom class'         => ['App\\Config\\TenantProcessor'],
            'wrong case'           => ['defaultsprocessor'],
            'partial match'        => ['Casting'],
            'trailing whitespace'  => ['CastingProcessor '],
            'unqualified'          => ['Defaults'],
        ];
    }

    /**
     * @dataProvider unknownStageNames
     */
    public function testTryFromReturnsNullForUnknownStages(string $value): void
    {
        verify(ProcessorStage::tryFrom($value))->null();
    }

    public function testWireValuesAreStable(): void
    {
        verify(array_map(static fn (ProcessorStage $s): string => $s->value, ProcessorStage::cases()))
            ->equals([
                'DefaultsProcessor',
                'ValidationProcessor',
                'CastingProcessor',
                'IncludeProcessor',
                'VariableProcessor',
            ])
        ;
    }

    public function testEveryCaseRoundTripsThroughItsWireValue(): void
    {
        foreach (ProcessorStage::cases() as $case) {
            verify(ProcessorStage::fromString($case->value))->equals($case);
            verify(ProcessorStage::tryFrom($case->value))->equals($case);
        }
    }
}
