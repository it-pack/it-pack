<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Factory\ConstantPlacementFactory;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;

final class TypedNumericRewriteTest extends Unit
{
    private DisenchanterInterface $disenchanter;

    protected function setUp(): void
    {
        $this->disenchanter = DisenchanterFactory::create()->build();
    }

    public function testIntLiteralPromotedWithTypedRawDeclaration(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): void { $mask = 0x1F; }
            }
            PHP;

        $rewritten = $this->disenchanter->rewrite($code);

        // Type keyword + EXACT raw token (0x1F, not the parsed 31).
        $this->assertStringContainsString('private const int MASK = 0x1F;', $rewritten);
        // The literal occurrence is replaced, not the declaration's own value.
        $this->assertStringContainsString('$mask = self::MASK;', $rewritten);
        $this->assertStringNotContainsString('$mask = 0x1F;', $rewritten);
    }

    public function testFloatLiteralPromotedWithFloatType(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): void { $ratio = 3.14; }
            }
            PHP;

        $rewritten = $this->disenchanter->rewrite($code);

        $this->assertStringContainsString('private const float RATIO = 3.14;', $rewritten);
        $this->assertStringContainsString('$ratio = self::RATIO;', $rewritten);
    }

    public function testNumericAndStringConstsCoexist(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): void { $mask = 0x1F; }
            }
            PHP;

        $rewritten = $this->disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string ADMIN_USER = 'admin-user';", $rewritten);
        $this->assertStringContainsString('private const int MASK = 0x1F;', $rewritten);
        $this->assertStringContainsString('self::ADMIN_USER', $rewritten);
        $this->assertStringContainsString('self::MASK', $rewritten);
    }

    public function testRewriteIsIdempotentForNumbers(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): void { $mask = 0x1F; }
            }
            PHP;

        $first = $this->disenchanter->rewrite($code);
        $second = $this->disenchanter->rewrite($first);

        $this->assertSame($first, $second, 're-running on rewritten numeric code must not change it');
        $this->assertStringNotContainsString('self::MASK = self::MASK', $first);
        $this->assertSame(1, substr_count($first, 'private const int MASK'));
    }

    public function testSemConvStoresCanonicalIntValueAndAliases(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): void { $mask = 0x1F; }
            }
            PHP;

        $result = $this->disenchanter->rewriteWithSemConv($code);

        // Canonical (parsed) value, not the raw hex token, and an int type.
        $this->assertStringContainsString('public const int MASK = 31;', $result->semconvSource);
        $this->assertStringNotContainsString('public const int MASK = 0x1F;', $result->semconvSource);
        $this->assertStringNotContainsString('public const string MASK', $result->semconvSource);

        // The class constant aliases the registry.
        $this->assertStringContainsString('use Generated\SemConv;', $result->code);
        $this->assertStringContainsString('private const int MASK = SemConv::MASK;', $result->code);
    }

    public function testHexAndDecimalShareOneSemConvEntry(): void
    {
        $placement = new ConstantPlacementFactory()->build(
            className: 'C',
            constantName: 'MASK',
            value: '31',
            type: LiteralKind::Int->value,
            occurrences: [],
        );

        $collector = new SemConvCollector(new SemConvConfig());

        // A prior run already collected 0x1F (canonicalized to 31).
        $prior = $collector->collect([$placement]);
        $this->assertStringContainsString('public const int MASK = 31;', $prior);

        // A new run whose literal is the decimal 31 must merge, not collide.
        $merged = $collector->collect([$placement], $prior);
        $this->assertStringContainsString('public const int MASK = 31;', $merged);
        $this->assertSame(
            substr_count($merged, 'MASK = 31'),
            1,
            'hex and decimal resolve to one canonical SemConv entry',
        );
    }
}
