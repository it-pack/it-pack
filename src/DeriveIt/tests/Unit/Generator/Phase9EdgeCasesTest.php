<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use ItPack\DeriveIt\Generator\TypeSubstitutor;
use ReflectionMethod;

class Phase9EdgeCasesTest extends \Codeception\Test\Unit
{
    public function testThrowsCaptureIgnoresDocblockWithoutThrows(): void
    {
        $source = new InterfaceDeriver(config: new DeriveConfig(throwsCapture: true))
            ->deriveSource(\_Fixture\DocBlockNoThrowsClass::class)
        ;

        self::assertFalse(str_contains($source, '@throws'));
    }

    public function testThrowsCaptureOnMethodWithoutThrowsTag(): void
    {
        $source = new InterfaceDeriver(config: new DeriveConfig(throwsCapture: true))
            ->deriveSource(\_Fixture\UnionTypeClass::class)
        ;

        self::assertFalse(str_contains($source, '@throws'));
    }

    public function testThrowsCapturePreservesDescription(): void
    {
        $source = new InterfaceDeriver(config: new DeriveConfig(throwsCapture: true))
            ->deriveSource(\_Fixture\ThrowsDocClass::class)
        ;

        // Exact wording must survive (MethodSignatureFactory:154/:162 formatting).
        verify($source)->stringContainsString('@throws \RuntimeException when the wheel comes off');
        verify($source)->stringContainsString('@throws \DomainException if the input is invalid');
    }

    public function testSubstituteSelfStaticCanBeDisabled(): void
    {
        $source = new InterfaceDeriver(config: new DeriveConfig(substituteSelfStatic: false))
            ->deriveSource(\_Fixture\SelfStaticParentReturnClass::class)
        ;

        verify($source)->stringContainsString('function withStatic(): static');
        self::assertFalse(str_contains($source, 'function withStatic(): SelfStaticParentReturnClassInterface'));
        verify($source)->stringContainsString('function withSelf(): SelfStaticParentReturnClass');
    }

    public function testPerTraitHonoursOnlyMethods(): void
    {
        $source = new PerTraitInterfaceDeriver(config: new DeriveConfig()->withOnlyMethods(['fromTrait']))
            ->deriveSource(\_Fixture\UsesMultipleTraitsClass::class)
        ;

        verify($source)->stringContainsString('fromTrait');
        self::assertFalse(str_contains($source, 'overridden'));
        self::assertFalse(str_contains($source, 'ownMethod'));
    }

    public function testTypeSubstitutorRewritesConstantExpressions(): void
    {
        $substitutor = new TypeSubstitutor(['Foo' => 'FooInterface']);

        verify($substitutor->rewriteConstantExpression('Foo::BAR'))->equals('FooInterface::BAR');
        verify($substitutor->rewriteConstantExpression('Foo::BAR'))->notEquals('Foo::BAR');

        $noMap = new TypeSubstitutor();
        verify($noMap->rewriteConstantExpression('Foo::BAR'))->equals('Foo::BAR');

        $other = new TypeSubstitutor(['Other' => 'OtherInterface']);
        verify($other->rewriteConstantExpression('Foo::BAR'))->equals('Foo::BAR');
    }

    public function testTypeSubstitutorPrefixNoMatchUnchanged(): void
    {
        $type = new ReflectionMethod(\_Fixture\NamespacePrefixHost::class, 'useExternal')
            ->getParameters()[0]->getType()
        ;
        $substitutor = new TypeSubstitutor([], null, null, ['Dep\\' => 'Dep\\Port\\']);

        verify($substitutor->substitute($type))->equals('Other\\VendorThing');
    }

    public function testIncludeArrayConstants(): void
    {
        $interface = new InterfaceDeriver(config: new DeriveConfig(includeConstants: true))
            ->derive(\_Fixture\ArrayConstantClass::class)
        ;

        verify(array_keys($interface->getConstants()))->equals(['SCALAR_ITEMS', 'NAME']);
    }

    public function testFailOnEmptyDoesNotThrowWhenConstantsPresent(): void
    {
        $config = new DeriveConfig()->withFailOnEmpty(true)->withIncludeConstants(true);

        // TypedConstantsClass has a constant but no methods: empty surface would
        // otherwise escalate, but the constant satisfies the surface.
        $interface = new InterfaceDeriver(config: $config)->derive(\_Fixture\TypedConstantsClass::class);

        verify(array_keys($interface->getConstants()))->equals(['VERSION']);
    }

    public function testFamilyTransitiveClosureResolvesChains(): void
    {
        $method = new ReflectionMethod(FamilyDeriver::class, 'transitiveClosure');
        $method->setAccessible(true);

        $result = $method->invoke(new FamilyDeriver(), ['A' => 'B', 'B' => 'C']);

        verify($result)->equals(['A' => 'C', 'B' => 'C']);
    }
}
