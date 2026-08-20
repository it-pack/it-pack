<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;

final class NumericDetectionTest extends Unit
{
    private function scan(string $code): array
    {
        return DetectorFactory::create()->build()->scanSource($code);
    }

    /**
     * @param list<object> $literals
     * @return list<NumberLiteral>
     */
    private function numbers(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            static fn ($lit): bool => $lit instanceof NumberLiteral,
        ));
    }

    public function testUnitSuffixProducesNamingContext(): void
    {
        $nums = $this->numbers($this->scan(
            '<?php class C { public function f(): int { $timeoutMs = 30000; return $timeoutMs; } }',
        ));

        $this->assertNotEmpty($nums);
        $ctx = $nums[0]->namingContext;
        $this->assertNotNull($ctx);
        $this->assertSame('timeoutMs', $ctx->variableName);
        $this->assertSame('ms', $ctx->unit);
        $this->assertTrue($ctx->hasHint());
    }

    public function testNamelessNumberCarriesNoHint(): void
    {
        $nums = $this->numbers($this->scan(
            '<?php class C { public function f(): int { if ($x === 3) { return 1; } return 2; } }',
        ));

        $this->assertNotEmpty($nums);
        foreach ($nums as $num) {
            $this->assertFalse($num->namingContext?->hasHint() ?? false, 'bare numbers carry no hint');
        }
    }

    public function testFilterKeepsHintedNumberAndDropsNameless(): void
    {
        $factory = DetectorFactory::create();
        $code = '<?php class C { public function f(): int { $timeoutMs = 30000; if ($x === 3) { return 0; } return $timeoutMs; } }';

        $kept = $factory->buildFilter()->filterCandidates($factory->build()->scanSource($code));
        $keptNums = $this->numbers($kept);

        // Only the hinted 30000 survives (the '3' is nameless, the '0' trivial).
        $this->assertCount(1, $keptNums);
        $this->assertSame('30000', $keptNums[0]->value);
    }

    public function testTrivialValuesAreExcluded(): void
    {
        $factory = DetectorFactory::create();
        $code = '<?php class C { public function f(): int { $flagTrue = 1; $flagFalse = 0; return $flagTrue + $flagFalse; } }';

        $kept = $factory->buildFilter()->filterCandidates($factory->build()->scanSource($code));

        $this->assertCount(0, $this->numbers($kept), '0 and 1 are trivial and excluded');
    }

    public function testDetectPromotesHintedNumberWithGeneratedName(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();
        $code = '<?php class C { public function f(): int { $timeoutMs = 30000; return $timeoutMs; } }';

        $candidates = $disenchanter->detect($code);

        $numeric = null;
        foreach ($candidates as $candidate) {
            if ($candidate->value === '30000') {
                $numeric = $candidate;
                break;
            }
        }

        $this->assertNotNull($numeric, 'hinted number is promoted');
        $this->assertSame('TIMEOUT_MS', $numeric->constantName);
    }

    public function testCommentHintProducesNamedCandidate(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();
        $code = <<<'PHP'
            <?php
            class C {
                public function f(): int {
                    // seconds
                    $t = 30;
                    return $t;
                }
            }
            PHP;

        $candidates = $disenchanter->detect($code);

        $numeric = null;
        foreach ($candidates as $candidate) {
            if ($candidate->value === '30') {
                $numeric = $candidate;
                break;
            }
        }

        $this->assertNotNull($numeric);
        $this->assertSame('T_SECONDS', $numeric->constantName);
    }

    public function testTwoEqualNumbersWithDifferentHintsStaySeparate(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();
        $code = <<<'PHP'
            <?php
            class C {
                public function a(): int { $timeoutMs = 100; return $timeoutMs; }
                public function b(): int { $durationMs = 100; return $durationMs; }
            }
            PHP;

        $candidates = $disenchanter->detect($code);

        $numericNames = array_values(array_map(
            static fn ($c) => $c->constantName,
            array_filter($candidates, static fn ($c) => $c->value === '100'),
        ));

        $this->assertContains('TIMEOUT_MS', $numericNames);
        $this->assertContains('DURATION_MS', $numericNames);
        $this->assertCount(2, $numericNames, 'distinct hints keep distinct candidates');
    }
}
