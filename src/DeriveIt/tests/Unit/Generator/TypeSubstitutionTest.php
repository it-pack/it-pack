<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\TypeSubstitutor;
use ReflectionMethod;

class TypeSubstitutionTest extends Unit
{
    public function testSubstitutorRewritesClassInUnionAndParameter(): void
    {
        $map = ['_Fixture\Node' => '_Fixture\NodeInterface'];
        $substitutor = new TypeSubstitutor($map);

        $returnType = new ReflectionMethod(\_Fixture\Node::class, 'getNext')->getReturnType();
        verify($substitutor->substitute($returnType))->equals('?_Fixture\NodeInterface');

        $paramType = new ReflectionMethod(\_Fixture\Tree::class, 'build')->getParameters()[0]->getType();
        verify($substitutor->substitute($paramType))->equals('_Fixture\NodeInterface');
    }

    public function testSubstitutorLeavesBuiltinsUntouched(): void
    {
        $substitutor = new TypeSubstitutor(['_Fixture\Node' => '_Fixture\NodeInterface']);

        $returnType = new ReflectionMethod(\_Fixture\Tree::class, 'count')->getReturnType();
        verify($substitutor->substitute($returnType))->equals('int');
    }

    public function testFamilyDerivationRewritesCrossReferences(): void
    {
        $derived = new FamilyDeriver()->derive([\_Fixture\Node::class, \_Fixture\Tree::class]);

        $tree = $derived[\_Fixture\Tree::class];
        verify($tree->getMethod('getRoot')->getReturnType())->equals('_Fixture\NodeInterface');
        verify($tree->getMethod('build')->getParameters()['seed']->getType())->equals('_Fixture\NodeInterface');

        $node = $derived[\_Fixture\Node::class];
        verify($node->getMethod('getNext')->getReturnType())->equals('_Fixture\NodeInterface');
        verify($node->getMethod('addChild')->getParameters()['child']->getType())->equals('_Fixture\NodeInterface');
    }

    public function testFamilySourceReferencesDerivedInterfaces(): void
    {
        $sources = DeriveIt::deriveFamily([\_Fixture\Node::class, \_Fixture\Tree::class]);

        $treeSource = $sources[\_Fixture\Tree::class];
        verify($treeSource)->stringContainsString('interface TreeInterface');
        verify($treeSource)->stringContainsString('function getRoot(): NodeInterface');
        verify($treeSource)->stringContainsString('function build(NodeInterface $seed): NodeInterface');
    }

    public function testExplicitOverrideWinsOverAutoDetection(): void
    {
        $config = new DeriveConfig(typeSubstitutionMap: ['_Fixture\Node' => '_Fixture\CustomNode']);
        $interface = new InterfaceDeriver(config: $config)->derive(\_Fixture\Tree::class);

        verify($interface->getMethod('getRoot')->getReturnType())->equals('_Fixture\CustomNode');
    }

    public function testFlatDerivationWithoutMapLeavesNativeType(): void
    {
        $interface = new InterfaceDeriver()->derive(\_Fixture\Tree::class);

        verify($interface->getMethod('getRoot')->getReturnType())->equals('_Fixture\Node');
        verify($interface->getMethod('build')->getParameters()['seed']->getType())->equals('_Fixture\Node');
    }
}
