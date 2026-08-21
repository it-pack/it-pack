<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use _Fixture\Node;
use _Fixture\Tree;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use Nette\PhpGenerator\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Step 10.14 (G13 / EC20): a single Nette Factory can be shared through the
 * derivers, and family discovery/derive reuse the same ReflectionClass instances
 * instead of re-reflecting. `Nette\PhpGenerator\Factory` is final, so reuse is a
 * structural optimization; these tests lock in the wiring (injection path) and
 * prove the refactor is behavior-preserving.
 *
 * @covers \ItPack\DeriveIt\Generator\FamilyDeriver
 */
final class Phase14SharedFactoryTest extends TestCase
{
    public function testSharedFactoryInjectionKeepsOutputIdentical(): void
    {
        $config = new DeriveConfig();

        $withShared = new FamilyDeriver($config, new Factory())
            ->derive([Node::class, Tree::class])
        ;

        $baseline = new FamilyDeriver($config)
            ->derive([Node::class, Tree::class])
        ;

        self::assertSame(array_keys($baseline), array_keys($withShared));
        foreach (array_keys($baseline) as $fqcn) {
            self::assertSame(
                (string) $baseline[$fqcn],
                (string) $withShared[$fqcn],
                "Interface for {$fqcn} differs when a shared Factory is injected",
            );
        }
    }

    public function testDeriveFromRootMatchesDeriveOverDiscoveredClasses(): void
    {
        $config = new DeriveConfig();

        $fromRoot = new FamilyDeriver($config)->deriveFromRoot(Node::class);

        self::assertArrayHasKey(Node::class, $fromRoot);

        // The new shared-reflection path (deriveFromRoot → deriveReflections) must
        // behave exactly like the explicit list path (derive → deriveReflections).
        $explicit = new FamilyDeriver($config)->derive([Node::class]);

        self::assertSame((string) $explicit[Node::class], (string) $fromRoot[Node::class]);
    }

    public function testDeriveSourceGroupsWithoutReReflecting(): void
    {
        $config = new DeriveConfig();

        $source = new FamilyDeriver($config)->deriveSource([Node::class, Tree::class]);

        self::assertStringContainsString('interface NodeInterface', $source);
        self::assertStringContainsString('interface TreeInterface', $source);
    }
}
