<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;

final class MagicLiteralVisitorTest extends Unit
{
    private function scan(string $code): array
    {
        return DetectorFactory::create()->build()->scanSource($code);
    }

    private function numbers(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            static fn ($lit): bool => $lit instanceof NumberLiteral,
        ));
    }

    public function testDetectsHexIntegerPreservingRawToken(): void
    {
        // 0x1F is a class constant's own declaration value (excluded); the
        // bare `31` in the body is a genuine numeric usage site.
        $literals = $this->scan(
            '<?php class C { const MASK = 0x1F; public function f(): int { return 31; } }',
        );

        $numbers = $this->numbers($literals);

        $this->assertCount(1, $numbers, 'class-const numeric value is not a usage site');
        $lit = $numbers[0];
        $this->assertSame(LiteralKind::Int->value, $lit->kind);
        $this->assertSame('31', $lit->value);
        $this->assertSame('31', $lit->raw);
    }

    public function testDetectsNumericSeparatorsAsRawToken(): void
    {
        $literals = $this->scan(
            '<?php class C { public function f(): int { return 1_000_000; } }',
        );

        $numbers = $this->numbers($literals);

        $this->assertCount(1, $numbers);
        $this->assertSame('1000000', $numbers[0]->value);
        $this->assertSame('1_000_000', $numbers[0]->raw);
    }

    public function testDetectsFloatLiteral(): void
    {
        $literals = $this->scan(
            '<?php class C { public function f(): float { return 3.14; } }',
        );

        $numbers = $this->numbers($literals);

        $this->assertCount(1, $numbers);
        $this->assertSame(LiteralKind::Float->value, $numbers[0]->kind);
        $this->assertSame('3.14', $numbers[0]->raw);
    }

    public function testNumericLiteralsAreDetectedButExcludedFromPromotionInP1(): void
    {
        $factory = DetectorFactory::create();
        $literals = $factory->build()->scanSource(
            '<?php class C { public function f(): int { return 1_000_000; } }',
        );

        // Detection records the number; the filter still excludes non-strings
        // from promotion until naming lands (P2). So nothing is promotable yet.
        $this->assertNotEmpty($this->numbers($literals));
        $this->assertCount(0, $factory->buildFilter()->filterCandidates($literals));
    }
}
