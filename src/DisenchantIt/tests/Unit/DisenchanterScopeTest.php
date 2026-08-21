<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;

final class DisenchanterScopeTest extends Unit
{
    private DisenchanterInterface $disenchanter;

    protected function setUp(): void
    {
        // The composition root's default scope opts out of test/fixture/var
        // directories.
        $this->disenchanter = DisenchanterFactory::create()->build();
    }

    private const CODE = <<<'PHP'
        <?php
        class C {
            public function a(): string { return 'admin-user'; }
            public function b(): string { return 'admin-user'; }
        }
        PHP;

    public function testDetectAnalysesANonIgnoredPath(): void
    {
        $candidates = $this->disenchanter->detect(self::CODE, 'src/C.php');

        $this->assertArrayHasKey('admin-user', $candidates);
    }

    public function testDetectSkipsAnIgnoredTestPath(): void
    {
        $this->assertSame([], $this->disenchanter->detect(self::CODE, 'tests/C.php'));
    }

    public function testRewriteLeavesAnIgnoredTestFileUnchanged(): void
    {
        $rewritten = $this->disenchanter->rewrite(self::CODE, 1, 'tests/C.php');

        $this->assertSame(self::CODE, $rewritten, 'ignored test file must not be rewritten');
    }
}
