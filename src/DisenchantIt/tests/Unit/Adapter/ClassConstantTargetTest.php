<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\Rewriting\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\Rewriting\LineBasedRewriter;
use ItPack\DisenchantIt\Factory\ConstantPlacementFactory;
use ItPack\DisenchantIt\ValueObject\Candidate\Candidate;
use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;
use ItPack\DisenchantIt\ValueObject\Source\NamingContext;

final class ClassConstantTargetTest extends Unit
{
    private function target(): ClassConstantTarget
    {
        return new ClassConstantTarget(
            ConstantPlacementFactory::create(),
            ConstantNameResolver::create(),
        );
    }

    /**
     * @param list<string> $classes
     */
    private function candidateInClasses(array $classes): Candidate
    {
        $occurrences = [];
        $line = 10;
        foreach ($classes as $class) {
            $occurrences[] = new StringLiteral(
                value: 'admin-user',
                line: $line++,
                role: LiteralRole::Unclassified,
                className: $class,
                methodName: 'f',
                raw: "'admin-user'",
                namingContext: new NamingContext(variableName: 'status'),
            );
        }

        return new Candidate('admin-user', 'ADMIN_USER', $occurrences);
    }

    public function testSupportsAnyCandidate(): void
    {
        $target = $this->target();
        $group = new GroupedCandidate($this->candidateInClasses(['C']));

        $this->assertTrue($target->supports($group));
    }

    public function testPerClassEmitsOneConstPerClassAndSelfReferences(): void
    {
        $target = $this->target();
        $group = new GroupedCandidate($this->candidateInClasses(['Order', 'Invoice']));

        $artifact = $target->build($group);

        $this->assertSame(TargetKind::Const, $artifact->kind);
        $this->assertCount(2, $artifact->insertions, 'one insertion per distinct class');
        $this->assertCount(2, $artifact->replacements);
        $this->assertCount(2, $artifact->placements, 'one placement per class for SemConv');

        foreach ($artifact->replacements as $replacement) {
            $this->assertStringStartsWith('self::ADMIN_USER', $replacement->replacement);
            $this->assertSame("'admin-user'", $replacement->needle);
        }

        $declarationText = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString('private const string ADMIN_USER =', $declarationText);
        $this->assertFalse($artifact->insertions[0]->topLevel);
    }

    public function testSingleClassProducesByteIdenticalDeclaration(): void
    {
        $target = $this->target();
        $group = new GroupedCandidate($this->candidateInClasses(['C']));

        $artifact = $target->build($group);

        // Mirrors the historical LineBasedRewriter declaration line exactly.
        $this->assertSame(
            '    private const string ADMIN_USER = \'admin-user\';',
            $artifact->insertions[0]->lines[0],
        );
    }
}
