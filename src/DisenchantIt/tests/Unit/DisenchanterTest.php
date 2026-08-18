<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;

final class DisenchanterTest extends Unit
{
    private DisenchanterInterface $disenchanter;

    protected function setUp(): void
    {
        $this->disenchanter = DisenchanterFactory::create()->build();
    }

    public function testDetectsGroupedNamedCandidates(): void
    {
        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
                public function c(): string { return 'role:admin'; }
                public function fail(): void { throw new \RuntimeException('boom'); }
            }
            PHP;

        $candidates = $this->disenchanter->detect($code);

        $this->assertArrayHasKey('admin-user', $candidates);
        $this->assertArrayHasKey('role:admin', $candidates);
        $this->assertArrayNotHasKey('boom', $candidates, 'exception message is excluded');

        $admin = $candidates['admin-user'];
        $this->assertInstanceOf(CandidateInterface::class, $admin);
        $this->assertSame('ADMIN_USER', $admin->constantName);
        $this->assertSame(2, $admin->count(), 'repeated literal grouped into one candidate');
    }

    public function testEmptySourceYieldsNoCandidates(): void
    {
        $this->assertSame([], $this->disenchanter->detect('<?php class C {}'));
    }
}
