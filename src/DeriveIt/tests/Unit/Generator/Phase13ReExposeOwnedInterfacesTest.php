<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ReflectionClass;

class Phase13ReExposeOwnedInterfacesTest extends Unit
{
    public function testReExposesOwnedGlobalInterface(): void
    {
        $source = DeriveIt::deriveWithResult(
            \_Fixture\OwnsCountable::class,
            new DeriveConfig(reExposeOwnedInterfaces: ['Countable']),
        )->source;

        self::assertStringContainsString('interface OwnsCountableInterface extends \\Countable', $source);

        $interface = new InterfaceDeriver(
            config: new DeriveConfig(reExposeOwnedInterfaces: ['Countable']),
        )->derive(new ReflectionClass(\_Fixture\OwnsCountable::class));

        verify($interface->getExtends())->equals(['Countable']);
    }

    public function testReExposesOwnedUserlandInterface(): void
    {
        $source = DeriveIt::deriveWithResult(
            \_Fixture\OwnsPreExisting::class,
            new DeriveConfig(reExposeOwnedInterfaces: ['_Fixture\\PreExistingInterface']),
        )->source;

        self::assertStringContainsString(
            'interface OwnsPreExistingInterface extends PreExistingInterface',
            $source,
        );
    }

    public function testThirdPartyInterfaceIsNotReExposedByDefault(): void
    {
        $source = DeriveIt::derive(\_Fixture\OwnsPreExisting::class);

        self::assertStringContainsString('interface OwnsPreExistingInterface', $source);
        self::assertStringNotContainsString('extends ', $source);

        $interface = new InterfaceDeriver()->derive(new ReflectionClass(\_Fixture\OwnsPreExisting::class));
        verify($interface->getExtends())->equals([]);
    }

    public function testUnimplementedOwnedInterfaceIsSkipped(): void
    {
        // `OwnsCountable` does not implement `PreExistingInterface`; listing it in
        // the allow-list must not fabricate an `extends` the adaptee cannot satisfy.
        $interface = new InterfaceDeriver(
            config: new DeriveConfig(reExposeOwnedInterfaces: ['_Fixture\\PreExistingInterface']),
        )->derive(new ReflectionClass(\_Fixture\OwnsCountable::class));

        verify($interface->getExtends())->equals([]);
    }

    public function testReExposeHonoredInFamilyMode(): void
    {
        $interfaces = new FamilyDeriver(
            new DeriveConfig(reExposeOwnedInterfaces: ['Countable']),
        )->derive([\_Fixture\OwnsCountable::class]);

        verify($interfaces[\_Fixture\OwnsCountable::class]->getExtends())
            ->equals(['Countable'])
        ;
    }

    public function testWithReExposeOwnedInterfacesWitherDerivesExtends(): void
    {
        $config = new DeriveConfig()->withReExposeOwnedInterfaces(['Countable']);

        $interface = new InterfaceDeriver(config: $config)
            ->derive(new ReflectionClass(\_Fixture\OwnsCountable::class))
        ;

        verify($interface->getExtends())->equals(['Countable']);
    }

    public function testAssertSelfContainedStillRejectsUnlistedExtends(): void
    {
        $interface = new \Nette\PhpGenerator\InterfaceType('Leaky');
        $interface->setExtends(\_Fixture\PreExistingInterface::class);

        $this->expectException(LeakageDetectedException::class);
        InterfaceDeriver::assertSelfContained($interface, ['Countable']);
    }

    public function testAssertSelfContainedAllowsListedExtends(): void
    {
        $interface = new \Nette\PhpGenerator\InterfaceType('Owned');
        $interface->setExtends(\_Fixture\PreExistingInterface::class);

        InterfaceDeriver::assertSelfContained($interface, ['_Fixture\\PreExistingInterface']);

        verify($interface->getExtends())
            ->equals(['_Fixture\\PreExistingInterface'])
        ;
    }
}
