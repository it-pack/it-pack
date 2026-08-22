<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;

final class Phase5SyntacticPositionsTest extends Unit
{
    public function testLiteralInAttributeArgumentIsPromoted(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            class C {
                #[Route('/users')]
                public function list(): void {}
                #[Route('/users')]
                public function other(): void {}
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string USERS = '/users';", $rewritten);
        $this->assertStringContainsString('#[Route(self::USERS)]', $rewritten);
        $this->assertStringNotContainsString("#[Route('/users')]", $rewritten);
    }

    public function testLiteralInPropertyDefaultIsPromoted(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            class C {
                private string $status = 'active';
                private string $otherStatus = 'active';
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string ACTIVE = 'active';", $rewritten);
        $this->assertStringContainsString('self::ACTIVE', $rewritten);
        $this->assertStringNotContainsString("private string \$status = 'active';", $rewritten);
    }

    public function testLiteralInMatchArmIsPromoted(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function label(string $key): string {
                    return match ($key) {
                        'pending' => 'Pending',
                        'pending' => 'Pending',
                        default => 'Unknown',
                    };
                }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string PENDING = 'pending';", $rewritten);
        $this->assertStringContainsString('self::PENDING', $rewritten);
    }

    public function testConstantInitializerValueIsNotRepromoted(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            class C {
                private const string STATUS = 'active';
                public function useIt(): string { return 'active'; }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        // The constant's own declared value stays exactly as written.
        $this->assertStringContainsString("private const string STATUS = 'active';", $rewritten);
        // The genuine usage site is promoted to a new const.
        $this->assertStringContainsString("private const string ACTIVE = 'active';", $rewritten);
    }
}
