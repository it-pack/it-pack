<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveItFactory;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Reflection\InMemoryReflectionCache;

/**
 * Exercises the composition-root factory (F2 / R.2): all four derivers build through
 * it and produce output identical to the direct construction path.
 */
class DeriveItFactoryTest extends Unit
{
    private const FIXTURE_CLASS = \_Fixture\Node::class;

    public function testFactoryProducesIdenticalFlatOutput(): void
    {
        $factory = new DeriveItFactory(new DeriveConfig(), new InMemoryReflectionCache());

        $viaFactory = $factory->interfaceDeriver()->deriveSource(self::FIXTURE_CLASS);
        $direct = new InterfaceDeriver(config: new DeriveConfig())->deriveSource(self::FIXTURE_CLASS);

        verify($viaFactory)->equals($direct);
    }

    public function testFactoryAssemblesAllDerivers(): void
    {
        $cache = new InMemoryReflectionCache();
        $factory = new DeriveItFactory(new DeriveConfig(), $cache);

        $derived = $factory->familyDeriver()->derive([self::FIXTURE_CLASS]);
        self::assertArrayHasKey(self::FIXTURE_CLASS, $derived);

        $interface = $factory->interfaceDeriver()->derive(self::FIXTURE_CLASS);
        verify($factory->adapterGenerator($interface, new \ReflectionClass(self::FIXTURE_CLASS))->generateSource())
            ->stringContainsString('Adapter')
        ;

        $factory->perTraitDeriver()->deriveTraitInterfaces(new \ReflectionClass(self::FIXTURE_CLASS));
    }
}
