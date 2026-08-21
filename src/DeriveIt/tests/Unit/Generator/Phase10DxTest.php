<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use _Fixture\FinalClass;
use _Fixture\StaticHeavyClass;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DerivationResult;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\DeriveDiff;

/**
 * Phase 10 Step 10.10 (G9 / EC15 / D2 / D5): metadata + batch + diff DX APIs.
 */
class Phase10DxTest extends \Codeception\Test\Unit
{
    public function testAnalyzeReturnsResultWithoutRenderingSource(): void
    {
        $result = DeriveIt::analyze(FinalClass::class);

        verify($result)->instanceOf(DerivationResult::class);
        verify($result->fqcn)->equals('_Fixture\FinalClassInterface');
        verify($result->shortName)->equals('FinalClassInterface');
        self::assertContains('publicApi', $result->methods);
        verify($result->source)->null();
        verify($result->interface)->instanceOf(\Nette\PhpGenerator\InterfaceType::class);
    }

    public function testAnalyzeHonorsCallerConfig(): void
    {
        $default = DeriveIt::analyze(StaticHeavyClass::class);
        verify($default->methods)->equals(['instanceMethod']);

        $withStatic = DeriveIt::analyze(
            StaticHeavyClass::class,
            new DeriveConfig(includeStatic: true),
        );
        self::assertContains('staticPublicMethod', $withStatic->methods);
        self::assertContains('instanceMethod', $withStatic->methods);
    }

    public function testDeriveManyReturnsKeyedResultsWithSource(): void
    {
        $results = DeriveIt::deriveMany([FinalClass::class, StaticHeavyClass::class]);

        self::assertCount(2, $results);
        self::assertArrayHasKey(FinalClass::class, $results);
        self::assertArrayHasKey(StaticHeavyClass::class, $results);

        $final = $results[FinalClass::class];
        verify($final)->instanceOf(DerivationResult::class);
        // deriveMany produces full results (source present) so callers can persist.
        self::assertStringContainsString('interface FinalClassInterface', $final->source);
        self::assertContains('publicApi', $final->methods);
    }

    public function testDiffReportsNoDriftForFreshlyGeneratedSource(): void
    {
        $generated = DeriveIt::derive(FinalClass::class);

        $diff = DeriveIt::diff(FinalClass::class, $generated);

        verify($diff)->instanceOf(DeriveDiff::class);
        self::assertFalse($diff->drift);
        self::assertEmpty($diff->diff);
        self::assertSame($generated, $diff->generated);
        self::assertSame($generated, $diff->existing);
    }

    public function testDiffReportsDriftWithUnifiedDiff(): void
    {
        $generated = DeriveIt::derive(FinalClass::class);
        $stale = $generated . "\ninterface StaleMarkerInterface {}\n";

        $diff = DeriveIt::diff(FinalClass::class, $stale);

        self::assertTrue($diff->drift);
        self::assertStringContainsString('@@', $diff->diff);
        self::assertStringContainsString('- interface StaleMarkerInterface {}', $diff->diff);
        self::assertSame($generated, $diff->generated);
    }
}
