<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use _Fixture\Collaborator;
use _Fixture\FluentBuilderClass;
use _Fixture\Service;
use _Fixture\Stubs\ClassD;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;

/**
 * Phase 10 Step 10.11 (G10 / G11 / EC14 / EC16 / B4): config presets + validation
 * + method-renames applied uniformly across flat / per-trait / family.
 */
class Phase10PresetsTest extends \Codeception\Test\Unit
{
    public function testStrictPresetEscalatesLeakageAndEmpties(): void
    {
        $config = DeriveConfig::strict();

        self::assertTrue($config->auditLeakage);
        self::assertTrue($config->failOnEmpty);
        self::assertTrue($config->substituteSelfStatic);
        self::assertFalse($config->includeStatic);
    }

    public function testForAdapterPresetEnablesStaticAndSelf(): void
    {
        $config = DeriveConfig::forAdapter();

        self::assertTrue($config->includeStatic);
        self::assertTrue($config->substituteSelfStatic);
    }

    public function testForFamilyPresetEnablesSelfSubstitution(): void
    {
        $config = DeriveConfig::forFamily();

        self::assertTrue($config->substituteSelfStatic);
    }

    public function testContradictoryOnlyAndSkipMethodsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('onlyMethods');

        new DeriveConfig(onlyMethods: ['save'], skipMethods: ['save']);
    }

    public function testEmptyRenameTargetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DeriveConfig(methodRenames: ['foo' => '']);
    }

    public function testCopyWithRejectsUnknownOverrideKeys(): void
    {
        // Withers address constructor parameters by string key; the guard makes a
        // renamed parameter fail loudly here instead of silently breaking a wither.
        $copyWith = new \ReflectionMethod(DeriveConfig::class, 'copyWith');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown DeriveConfig override key "notARealProperty"');

        $copyWith->invoke(new DeriveConfig(), ['notARealProperty' => true]);
    }

    public function testFlatMethodRename(): void
    {
        $config = new DeriveConfig()->withMethodRenames(['withName' => 'withNameRenamed']);
        $result = DeriveIt::deriveWithResult(FluentBuilderClass::class, $config);

        self::assertContains('withNameRenamed', $result->methods);
        self::assertNotContains('withName', $result->methods);
        self::assertStringContainsString('withNameRenamed', $result->source);
    }

    public function testFlatRenameCollisionThrows(): void
    {
        $config = new DeriveConfig()->withMethodRenames([
            'withName' => 'dup',
            'withVersion' => 'dup',
        ]);

        $this->expectException(NamingCollisionException::class);

        DeriveIt::deriveWithResult(FluentBuilderClass::class, $config);
    }

    public function testPerTraitMethodRename(): void
    {
        $config = new DeriveConfig()->withMethodRenames(['traitEMethod' => 'traitERenamed']);
        $interfaces = DeriveIt::deriveTraitInterfaces(ClassD::class, $config);

        $names = array_map(static fn (\Nette\PhpGenerator\Method $m): string => $m->getName(), $interfaces['TraitE']->getMethods());
        self::assertContains('traitERenamed', $names);
        self::assertNotContains('traitEMethod', $names);
    }

    public function testFamilyMethodRenameIsUniform(): void
    {
        $config = new DeriveConfig()->withMethodRenames(['name' => 'nameRenamed']);
        $interfaces = new FamilyDeriver($config)->derive([Service::class, Collaborator::class]);

        $collaboratorNames = array_map(static fn (\Nette\PhpGenerator\Method $m): string => $m->getName(), $interfaces[Collaborator::class]->getMethods());
        self::assertContains('nameRenamed', $collaboratorNames);
        self::assertNotContains('name', $collaboratorNames);

        $serviceNames = array_map(static fn (\Nette\PhpGenerator\Method $m): string => $m->getName(), $interfaces[Service::class]->getMethods());
        self::assertContains('nameRenamed', $serviceNames);
    }
}
