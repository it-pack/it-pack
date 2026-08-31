<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Detection\ContentClassifier;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;
use ItPack\DisenchantIt\ValueObject\Source\SourceCode;

final class Phase4StringCoverageTest extends Unit
{
    private function scan(string $code): array
    {
        return DetectorFactory::create()->build()->scanSource(new SourceCode($code));
    }

    private function strings(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            static fn ($lit): bool => $lit instanceof StringLiteral,
        ));
    }

    // --- S4.1: heredoc / nowdoc coverage ---

    public function testHeredocBodyDetectedWithSqlSubkind(): void
    {
        $code = <<<'PHP'
            <?php
            class Repo {
                public function find(): void {
                    $sql = <<<SQL
            SELECT name FROM users
            SQL;
                }
            }
            PHP;

        $strings = $this->strings($this->scan($code));

        $this->assertCount(1, $strings, 'heredoc body is collected as one string literal');
        $lit = $strings[0];
        $this->assertStringStartsWith('SELECT name FROM users', $lit->value);
        $this->assertSame(LiteralSubkind::Sql, $lit->subkind);
        $this->assertGreaterThan($lit->line, $lit->endLine, 'heredoc spans multiple lines');
    }

    public function testHeredocWithInterpolationIsSkipped(): void
    {
        $code = <<<'PHP'
            <?php
            class Repo {
                public function find(int $id): void {
                    $sql = <<<SQL
            SELECT name FROM users WHERE id = $id
            SQL;
                }
            }
            PHP;

        // An interpolated heredoc cannot be promoted to a single constant.
        $this->assertCount(0, $this->strings($this->scan($code)));
    }

    public function testOversizedHeredocSkippedByDefault(): void
    {
        $big = str_repeat('lorem ipsum ', 5000); // ~60 KB, well over the 4 KB default cap
        $code = '<?php class C { public function f(): void { $x = <<<TXT' . "\n" . $big . "\n" . 'TXT; } }';

        // The oversized body is skipped at detection; no literal is collected.
        $this->assertCount(0, $this->strings($this->scan($code)));
    }

    public function testEndToEndHeredocPromotedToConstant(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php

            declare(strict_types=1);

            final class Repo
            {
                public function find(): void
                {
                    $sql = <<<SQL
            SELECT name FROM users
            SQL;

                    $other = <<<SQL
            SELECT name FROM users
            SQL;
                }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString(
            "private const string SELECT_NAME_FROM_USERS = 'SELECT name FROM users",
            $rewritten,
        );
        $this->assertStringContainsString('$sql = self::SELECT_NAME_FROM_USERS;', $rewritten);
        $this->assertStringContainsString('$other = self::SELECT_NAME_FROM_USERS;', $rewritten);

        // Second pass must not re-promote or alter the source.
        $this->assertSame($rewritten, $disenchanter->rewrite($rewritten));
    }

    // --- S4.2: FQCN detect-and-exclude + opt-in ::class converter ---

    public function testFqcnStringFlaggedAsClassnameString(): void
    {
        $classifier = new ContentClassifier();

        $this->assertTrue($classifier->isClassNameString('App\\Foo\\Bar'));
        $this->assertTrue($classifier->isClassNameString('App\\Foo\\Bar\\Baz'));
        $this->assertTrue($classifier->isClassNameString('App\\Foo'));
        $this->assertFalse($classifier->isClassNameString('admin-user'));
        $this->assertFalse($classifier->isClassNameString('App Foo Bar'));
    }

    public function testFqcnStringExcludedFromPromotionByDefault(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            final class C {
                public function f(): void {
                    $x = 'App\Foo\Bar';
                    $y = 'App\Foo\Bar';
                }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        // Default scope: FQCN strings are references, not magic values.
        $this->assertStringContainsString("'App\\Foo\\Bar'", $rewritten);
        $this->assertStringNotContainsString('::class', $rewritten);
    }

    public function testFqcnConvertedToClassConstWhenOptInEnabled(): void
    {
        $disenchanter = DisenchanterFactory::create(convertFqcnToClassConst: true)->build();

        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            final class C {
                public function f(): void {
                    $x = 'App\Foo\Bar';
                    $y = 'App\Foo\Bar';
                }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString('$x = Bar::class;', $rewritten);
        $this->assertStringContainsString('$y = Bar::class;', $rewritten);
        $this->assertStringNotContainsString("'App\\Foo\\Bar'", $rewritten);

        // No constant declaration: `::class` is engine-provided.
        $this->assertStringNotContainsString('const', $rewritten);

        $this->assertSame($rewritten, $disenchanter->rewrite($rewritten));
    }

    public function testConcatenatedFqcnLeftAlone(): void
    {
        $disenchanter = DisenchanterFactory::create(convertFqcnToClassConst: true)->build();

        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            final class C {
                public function f(string $x): void {
                    $y = "App\\Foo\\$x";
                }
            }
            PHP;

        // A concatenated string is not a pure FQCN literal, so it is untouched.
        $rewritten = $disenchanter->rewrite($code);
        $this->assertSame($code, $rewritten);
    }
}
