<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\AdapterGenerator;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\TypeSubstitutor;
use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;
use ReflectionMethod;

/**
 * Targeted assertions that pin down the exact rendered shape of generated
 * adapters / interfaces / substituted types so mutation testing cannot flip
 * branching conditions without a failing test.
 */
class MutationCoverageTest extends Unit
{
    private function hostReflection(): ReflectionClass
    {
        return new ReflectionClass(\_Fixture\NamespacePrefixHost::class);
    }

    public function testGenerateIsPublicAndReturnsClassType(): void
    {
        $interface = new InterfaceType('HostInterface');
        $generator = new AdapterGenerator($interface, $this->hostReflection());

        $class = $generator->generate();

        verify($class)->instanceOf(\Nette\PhpGenerator\ClassType::class);
        verify($class->getName())->equals('NamespacePrefixHostAdapter');
    }

    public function testStaticDelegatingRendersExactSignatureAndBody(): void
    {
        $interface = new InterfaceType('HostInterface');
        $method = $interface->addMethod('compute');
        $method->setStatic(true);
        $method->setReturnType('string');
        $method->addParameter('input')->setType('string');
        $method->addParameter('flag')->setType('int')->setDefaultValue(1);
        $byRef = $method->addParameter('accumulator');
        $byRef->setType('array');
        $byRef->setReference(true);

        $source = new AdapterGenerator($interface, $this->hostReflection())->generateSource();

        verify($source)->stringContainsString(
            'public static function compute(string $input, int $flag = 1, array &$accumulator): string',
        );
        verify($source)->stringContainsString(
            'return \\_Fixture\\NamespacePrefixHost::compute($input, $flag, $accumulator);',
        );
    }

    public function testInstanceVariadicRendersExactSignatureAndBody(): void
    {
        $interface = new InterfaceType('HostInterface');
        $method = $interface->addMethod('collect');
        $method->setReturnType('void');
        $method->addParameter('limit')->setType('int')->setDefaultValue(10);
        $method->addParameter('items')->setType('string');
        $method->setVariadic(true);

        $source = new AdapterGenerator($interface, $this->hostReflection())->generateSource();

        verify($source)->stringContainsString(
            'function collect(int $limit = 10, string ...$items): void',
        );
        verify($source)->stringContainsString('$this->adaptee->collect($limit, ...$items);');
    }

    public function testIntersectionReturnIsNotWrapped(): void
    {
        $interface = new InterfaceType('HostInterface');
        $method = $interface->addMethod('compose');
        $method->setReturnType('Foo&Bar');

        $source = new AdapterGenerator(
            $interface,
            $this->hostReflection(),
            ['Foo' => 'FooAdapter'],
        )->generateSource();

        verify($source)->stringContainsString('return $this->adaptee->compose();');
        verify($source)->stringNotContainsString('match (true)');
    }

    public function testWrapperWithLeadingSlashCollapsesToSingleSlash(): void
    {
        $interface = new InterfaceType('HostInterface');
        $method = $interface->addMethod('build');
        $method->setReturnType('Foo');

        $source = new AdapterGenerator(
            $interface,
            $this->hostReflection(),
            ['Foo' => '\\FooAdapter'],
        )->generateSource();

        verify($source)->stringContainsString('return new \\FooAdapter($this->adaptee->build());');
        verify($source)->stringNotContainsString('\\\\FooAdapter');
    }

    public function testNullableWrappedReturnBodyIsExact(): void
    {
        $substitution = ['_Fixture\Collaborator' => '_Fixture\CollaboratorInterface'];
        $wrapper = ['_Fixture\CollaboratorInterface' => '_Fixture\CollaboratorAdapter'];

        $source = DeriveIt::deriveAdapter(
            \_Fixture\Service::class,
            $wrapper,
            new DeriveConfig(typeSubstitutionMap: $substitution),
        );

        verify($source)->stringContainsString(
            'return $value === null ? null : new \\_Fixture\\CollaboratorAdapter($value);',
        );
    }

    public function testThrowsCaptureCommentIsExact(): void
    {
        $interface = new InterfaceDeriver(config: new DeriveConfig(throwsCapture: true))
            ->derive(\_Fixture\ThrowsDocClass::class)
        ;

        $comment = $interface->getMethod('run')->getComment();

        self::assertSame(
            "@throws \\RuntimeException when the wheel comes off\n@throws \\DomainException if the input is invalid",
            $comment,
        );
    }

    public function testConstantExpressionWithoutAnchorsIsUnchanged(): void
    {
        $substitutor = new TypeSubstitutor(['Foo' => 'FooInterface'], 'Self', 'Parent');

        self::assertSame('1Foo::BAR', $substitutor->rewriteConstantExpression('1Foo::BAR'));
        self::assertSame('Foo::BAR baz', $substitutor->rewriteConstantExpression('Foo::BAR baz'));
    }

    public function testConstantExpressionLeadingSlashIsStripped(): void
    {
        $substitutor = new TypeSubstitutor(['Foo' => 'FooInterface']);

        self::assertSame('FooInterface::BAR', $substitutor->rewriteConstantExpression('\\Foo::BAR'));
    }

    public function testSubstituteUnionPreservesEverySegment(): void
    {
        $substitutor = new TypeSubstitutor([]);
        $type = new ReflectionMethod(\_Fixture\UnionTypeClass::class, 'queryLeaf')->getReturnType();

        self::assertSame('array|string|int|float|bool|null', $substitutor->substitute($type));
    }

    public function testDeriveInterfaceDefaultExcludesStaticMethods(): void
    {
        $interface = DeriveIt::deriveInterface(\_Fixture\StaticHeavyClass::class);

        verify($interface->hasMethod('staticPublicMethod'))->false();
        verify($interface->hasMethod('instanceMethod'))->true();
    }
}
