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

    public function testRewritePromotesRepeatedLiteralIntoConstant(): void
    {
        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $rewritten = $this->disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string ADMIN_USER = 'admin-user';", $rewritten);
        $this->assertStringContainsString('self::ADMIN_USER', $rewritten);
        $this->assertStringNotContainsString("return 'admin-user';", $rewritten);
    }

    public function testRewriteRespectsMinOccurrences(): void
    {
        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'only-once'; }
            }
            PHP;

        // Below threshold: nothing promoted, source returned unchanged.
        $this->assertSame($code, $this->disenchanter->rewrite($code, 2));

        // At threshold 1: single occurrence is promoted.
        $this->assertStringContainsString('self::ONLY_ONCE', $this->disenchanter->rewrite($code, 1));
    }

    public function testRewriteIsIdempotent(): void
    {
        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $first = $this->disenchanter->rewrite($code);
        $second = $this->disenchanter->rewrite($first);

        $this->assertSame($first, $second, 're-running on rewritten code must not change it');
        $this->assertStringNotContainsString('self::ADMIN_USER = self::ADMIN_USER', $first);
    }

    public function testRewriteDoesNotRepromoteAnExistingConstValue(): void
    {
        $code = <<<'PHP'
            <?php
            class C {
                private const string KNOWN = 'shared-value';
                public function a(): string { return 'shared-value'; }
                public function b(): string { return 'shared-value'; }
            }
            PHP;

        $rewritten = $this->disenchanter->rewrite($code);

        // The pre-existing const's OWN declaration value is untouched.
        $this->assertStringContainsString("private const string KNOWN = 'shared-value';", $rewritten);
        // The genuine usage sites are still promoted to a new const.
        $this->assertStringContainsString("private const string SHARED_VALUE = 'shared-value';", $rewritten);
        $this->assertStringContainsString('self::SHARED_VALUE', $rewritten);
    }

    public function testRewriteWithSemConvCollectsAndAliases(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;

            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $result = $this->disenchanter->rewriteWithSemConv($code);

        // Shared SemConv registry contains the collected constant.
        $this->assertStringContainsString('interface SemConv', $result->semconvSource);
        $this->assertStringContainsString("public const string ADMIN_USER = 'admin-user';", $result->semconvSource);

        // The class constant now aliases the registry, with a use import added,
        // while the method bodies keep using self::.
        $this->assertStringContainsString('use Generated\\SemConv;', $result->code);
        $this->assertStringContainsString('private const string ADMIN_USER = SemConv::ADMIN_USER;', $result->code);
        $this->assertStringContainsString('self::ADMIN_USER', $result->code);
    }
}
