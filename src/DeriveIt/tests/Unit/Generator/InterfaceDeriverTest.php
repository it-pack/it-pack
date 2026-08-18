<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\MethodExtractor;
use ItPack\DeriveIt\Generator\MethodSignatureFactory;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use ReflectionClass;
use ReflectionMethod;

class InterfaceDeriverTest extends \Codeception\Test\Unit
{
    public function testDeriveReturnsInterfaceType(): void
    {
        $interface = DeriveIt::deriveInterface(\_Fixture\UnionTypeClass::class);

        verify($interface)->instanceOf(InterfaceType::class);
        verify($interface->getName())->equals('UnionTypeClassInterface');
    }

    public function testDeriveSourceReturnsRenderedString(): void
    {
        $source = DeriveIt::derive(\_Fixture\UnionTypeClass::class);

        verify($source)->stringContainsString('interface UnionTypeClassInterface');
        verify($source)->stringContainsString('function queryLeaf');
    }

    public function testFlatInterfaceHasNoExtendsLeakage(): void
    {
        $interface = new InterfaceDeriver()->derive(\_Fixture\ImplementsMultipleClass::class);

        verify($interface->getExtends())->equals([]);
    }

    public function testConstructorsAreDroppedByDefault(): void
    {
        $interface = new InterfaceDeriver()->derive(\_Fixture\ReadonlyClass::class);

        verify($interface->getMethods())->equals([]);
    }

    public function testStaticMethodsDroppedByDefaultButKeptWhenRequested(): void
    {
        $default = new InterfaceDeriver()->derive(\_Fixture\StaticHeavyClass::class);
        verify($default->hasMethod('staticPublicMethod'))->false();
        verify($default->hasMethod('instanceMethod'))->true();

        $withStatic = new InterfaceDeriver(config: new DeriveConfig(includeStatic: true))->derive(\_Fixture\StaticHeavyClass::class);
        verify($withStatic->hasMethod('staticPublicMethod'))->true();
    }

    public function testMagicMethodsDroppedByDefault(): void
    {
        $interface = new InterfaceDeriver()->derive(\_Fixture\MagicMethodsClass::class);

        verify($interface->hasMethod('__toString'))->false();
        verify($interface->hasMethod('__get'))->false();
        verify($interface->hasMethod('__set'))->false();
        verify($interface->hasMethod('__call'))->false();
        verify($interface->hasMethod('__invoke'))->false();
    }

    public function testTraitConflictResolvesToSingleMethod(): void
    {
        $interface = new InterfaceDeriver()->derive(\_Fixture\UsesTraitWithOverrideClass::class);

        $names = array_map(static fn (Method $m): string => $m->getName(), $interface->getMethods());
        verify(array_count_values($names)['overridden'] ?? 0)->equals(1);
    }

    public function testMethodSignatureIsAbstractWithoutBody(): void
    {
        $extractor = new MethodExtractor(new DeriveConfig());
        $methods = $extractor->extract(new ReflectionClass(\_Fixture\UnionTypeClass::class));

        $signature = new MethodSignatureFactory()->fromMethod($methods[0]);

        verify($signature->isAbstract())->true();
        verify($signature->getBody())->equals('');
        verify($signature->getVisibility())->null();
    }

    public function testExtractorFiltersByStaticFlag(): void
    {
        $onlyInstance = new MethodExtractor(new DeriveConfig())->extract(new ReflectionClass(\_Fixture\StaticHeavyClass::class));
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $onlyInstance);
        verify(in_array('staticPublicMethod', $names, true))->false();
        verify(in_array('instanceMethod', $names, true))->true();
    }

    public function testAssertSelfContainedRejectsExtends(): void
    {
        $interface = new InterfaceType('Leaky');
        $interface->setExtends(\_Fixture\PreExistingInterface::class);

        $this->expectException(\LogicException::class);
        InterfaceDeriver::assertSelfContained($interface);
    }
}
