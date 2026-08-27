<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\EmptyDerivationException;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Generator\AdapterGenerator;
use ItPack\DeriveIt\Generator\DerivationResult;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\Naming;
use ItPack\DeriveIt\Generator\TypeSubstitutor;
use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;
use ReflectionMethod;

class Phase9FeaturesTest extends \Codeception\Test\Unit
{
    public function testDeriveWithResultExposesStructuredSurface(): void
    {
        $result = DeriveIt::deriveWithResult(\_Fixture\SelfStaticParentReturnClass::class);

        verify($result)->instanceOf(DerivationResult::class);
        verify($result->fqcn)->equals('_Fixture\SelfStaticParentReturnClassInterface');
        verify($result->shortName)->equals('SelfStaticParentReturnClassInterface');
        self::assertContains('withSelf', $result->methods);
        verify($result->source)->stringContainsString('interface SelfStaticParentReturnClassInterface');
        // self/static/parent are recorded as substitutions (A1).
        self::assertArrayHasKey('self', $result->substitutions);
        verify($result->substitutions['self'])->equals('_Fixture\SelfStaticParentReturnClassInterface');
    }

    public function testConfigWithersAreImmutableAndParity(): void
    {
        $base = new DeriveConfig();

        $derived = $base
            ->withVisibility(DeriveConfig::VISIBILITY_PROTECTED)
            ->withIncludeStatic(true)
            ->withIncludeMagic(true)
            ->withDropConstruct(false)
            ->withDropDestruct(false)
            ->withTypeSubstitutionMap(['A' => 'B'])
            ->withSubstituteSelfStatic(false)
            ->withIncludeConstants(true)
            ->withOnlyMethods(['a'])
            ->withSkipMethods(['b'])
            ->withFailOnEmpty(true)
            ->withAttributePassThrough(true)
            ->withThrowsCapture(true)
        ;

        // Source unchanged.
        verify($base->includeStatic)->false();
        verify($base->includeConstants)->false();
        verify($base->failOnEmpty)->false();

        // Each flag flipped on the derived copy.
        verify($derived->visibility)->equals(DeriveConfig::VISIBILITY_PROTECTED);
        verify($derived->includeStatic)->true();
        verify($derived->includeMagic)->true();
        verify($derived->dropConstruct)->false();
        verify($derived->dropDestruct)->false();
        verify($derived->typeSubstitutionMap)->equals(['A' => 'B']);
        verify($derived->substituteSelfStatic)->false();
        verify($derived->includeConstants)->true();
        verify($derived->onlyMethods)->equals(['a']);
        verify($derived->skipMethods)->equals(['b']);
        verify($derived->failOnEmpty)->true();
        verify($derived->attributePassThrough)->true();
        verify($derived->throwsCapture)->true();
    }

    public function testIncludeConstantsOptIn(): void
    {
        $without = new InterfaceDeriver()->derive(\_Fixture\TypedConstantsClass::class);
        verify($without->getConstants())->equals([]);

        $with = new InterfaceDeriver(config: new DeriveConfig(includeConstants: true))
            ->derive(\_Fixture\TypedConstantsClass::class)
        ;
        verify(array_keys($with->getConstants()))->equals(['VERSION']);
    }

    public function testOnlyAndSkipMethodsFilter(): void
    {
        $only = new InterfaceDeriver(config: new DeriveConfig()->withOnlyMethods(['withName']))
            ->derive(\_Fixture\FluentBuilderClass::class)
        ;
        verify($only->hasMethod('withName'))->true();
        verify($only->hasMethod('withVersion'))->false();

        $skipped = new InterfaceDeriver(config: new DeriveConfig()->withSkipMethods(['withName']))
            ->derive(\_Fixture\FluentBuilderClass::class)
        ;
        verify($skipped->hasMethod('withName'))->false();
        verify($skipped->hasMethod('withVersion'))->true();
    }

    public function testFailOnEmptyEscalates(): void
    {
        $config = new DeriveConfig()->withFailOnEmpty(true);

        $this->expectException(EmptyDerivationException::class);
        new InterfaceDeriver(config: $config)->derive(\_Fixture\ReadonlyClass::class);
    }

    public function testAttributePassThroughOptIn(): void
    {
        $stripped = DeriveIt::derive(\_Fixture\AttributedClass::class);
        self::assertFalse(str_contains($stripped, 'Deprecated'));

        $kept = new InterfaceDeriver(config: new DeriveConfig(attributePassThrough: true))
            ->deriveSource(\_Fixture\AttributedClass::class)
        ;
        verify($kept)->stringContainsString('Deprecated');
    }

    public function testThrowsCaptureAddsDocblock(): void
    {
        $source = new InterfaceDeriver(config: new DeriveConfig(throwsCapture: true))
            ->deriveSource(\_Fixture\ThrowsDocClass::class)
        ;

        verify($source)->stringContainsString('@throws \RuntimeException');
        verify($source)->stringContainsString('@throws \DomainException');
    }

    public function testNamingCollisionWithConcreteTypeIsDetected(): void
    {
        $ns = 'Collide' . uniqid();
        eval("namespace $ns; class Target {}");
        eval("namespace $ns; class TargetInterface {}");

        $this->expectException(NamingCollisionException::class);
        Naming::assertNoCollision(new ReflectionClass($ns . '\Target'));
    }

    public function testFamilySourceGroupsByNamespace(): void
    {
        $source = DeriveIt::deriveFamilySource([\_Fixture\Tree::class, \_Fixture\Collaborator::class]);

        verify($source)->stringContainsString('interface TreeInterface');
        verify($source)->stringContainsString('interface CollaboratorInterface');
        // Both live in the same _Fixture namespace -> single namespace block.
        verify(substr_count($source, 'namespace _Fixture;'))->equals(1);
    }

    public function testFamilyAutoDiscoveryFromRoot(): void
    {
        $family = DeriveIt::deriveFamilyFromRoot(\_Fixture\Service::class);

        self::assertArrayHasKey(\_Fixture\Service::class, $family);
        self::assertArrayHasKey(\_Fixture\Collaborator::class, $family);
        verify($family[\_Fixture\Service::class])->stringContainsString('interface ServiceInterface');
    }

    public function testOwnedNamespacePrefixSubstitution(): void
    {
        // Owned-namespace rewriting is applied across a family derivation (C2).
        $source = DeriveIt::deriveFamilySource(
            [\_Fixture\NamespacePrefixHost::class],
            ['Prefix\\' => 'Prefix\\Port\\'],
        );

        // The owned-namespace reference is rewritten; the external one is not.
        verify($source)->stringContainsString('Port\OwnedService');
        verify($source)->stringContainsString('Other\VendorThing');
    }

    public function testAdapterWrapsUnionReturnPerSegment(): void
    {
        $interface = new InterfaceType('HostInterface');
        $method = $interface->addMethod('pick');
        $method->setReturnType('\_Fixture\PartOneInterface|\_Fixture\PartTwoInterface');

        $adapter = new AdapterGenerator(
            $interface,
            new ReflectionClass(\_Fixture\NamespacePrefixHost::class),
            [
                '\_Fixture\PartOneInterface' => '\_Fixture\PartOneAdapter',
                '\_Fixture\PartTwoInterface' => '\_Fixture\PartTwoAdapter',
            ],
        );

        $source = $adapter->generateSource();

        verify($source)->stringContainsString('match (true)');
        verify($source)->stringContainsString('PartOneAdapter');
        verify($source)->stringContainsString('PartTwoAdapter');
    }

    public function testTypeSubstitutorRewritesSelfStaticParent(): void
    {
        $substitutor = new TypeSubstitutor(
            ['Dep\\Foo' => 'Dep\\FooInterface'],
            'App\\SubjectInterface',
            'App\\BaseInterface',
            ['Prefix\\' => 'Prefix\\Port\\'],
        );

        // `static` designator resolves via the self FQCN.
        $staticType = new ReflectionMethod(\_Fixture\SelfStaticParentReturnClass::class, 'withStatic')->getReturnType();
        verify($substitutor->substitute($staticType))->equals('App\\SubjectInterface');

        // Owned-namespace prefix on a real class reference.
        $paramType = new ReflectionMethod(\_Fixture\NamespacePrefixHost::class, 'useOwned')
            ->getParameters()[0]->getType()
        ;
        verify($substitutor->substitute($paramType))->equals('Prefix\\Port\\OwnedService');

        // `parent` resolves to the parent concrete class; the map rewrites it.
        $parentType = new ReflectionMethod(\_Fixture\SelfStaticParentReturnClass::class, 'toParent')->getReturnType();
        $parentSubstitutor = new TypeSubstitutor(
            ['_Fixture\ConcreteChildClass' => '\_Fixture\ConcreteChildClassInterface'],
        );
        verify($parentSubstitutor->substitute($parentType))->equals('\_Fixture\ConcreteChildClassInterface');
    }

    public function testDefaultValueConstantRewrittenWhenMapped(): void
    {
        // A2: the constant reference inside a default-value expression is rewritten
        // through the substitution map (MethodSignatureFactory:116).
        $source = new InterfaceDeriver(config: new DeriveConfig(
            typeSubstitutionMap: ['SimpleBackedEnum' => 'SimpleBackedEnumInterface'],
        ))->deriveSource(\_Fixture\ConsumesEnumParamClass::class);

        verify($source)->stringContainsString('SimpleBackedEnumInterface::DEFAULT');
        self::assertFalse(str_contains($source, 'SimpleBackedEnum::DEFAULT'));
    }
}
