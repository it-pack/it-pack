<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\NameSlugifier;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NamingContext;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use ItPack\DisenchantIt\ValueObject\TargetKind;

final class BackedEnumTargetTest extends Unit
{
    private function closedCandidate(array $values): Candidate
    {
        $occurrences = [];
        $line = 10;
        foreach ($values as $value) {
            $occurrences[] = new StringLiteral(
                value: $value,
                line: $line++,
                role: LiteralRole::Unclassified,
                className: 'StatusMachine',
                methodName: 'label',
                raw: "'" . $value . "'",
                namingContext: new NamingContext(variableName: 'status'),
            );
        }

        return new Candidate($values[0], 'STATUS', $occurrences);
    }

    private function closedGroup(array $values): GroupedCandidate
    {
        return new GroupedCandidate($this->closedCandidate($values), true, 'status');
    }

    private function target(): BackedEnumTarget
    {
        return new BackedEnumTarget(new NameSlugifier());
    }

    public function testSupportsOnlyClosedSets(): void
    {
        $target = $this->target();

        $this->assertTrue($target->supports($this->closedGroup(['new', 'active', 'closed'])));
        $this->assertFalse($target->supports(new GroupedCandidate($this->closedCandidate(['lonely']))));
    }

    public function testBuildsTopLevelBackedEnumWithCasesAndReferences(): void
    {
        $target = $this->target();
        $artifact = $target->build($this->closedGroup(['new', 'active', 'closed']));

        $this->assertSame(TargetKind::Enum, $artifact->kind);
        $this->assertCount(3, $artifact->replacements);
        $this->assertSame([], $artifact->placements, 'enums are not collected into SemConv');

        $insertion = $artifact->insertions[0];
        $this->assertTrue($insertion->topLevel, 'enum is inserted at file top level');
        $this->assertSame('StatusMachine', $insertion->className);

        $enumSource = implode("\n", $insertion->lines);
        $this->assertStringContainsString('enum Status: string {', $enumSource);
        $this->assertStringContainsString("case New = 'new';", $enumSource);
        $this->assertStringContainsString("case Active = 'active';", $enumSource);
        $this->assertStringContainsString("case Closed = 'closed';", $enumSource);

        $byValue = [];
        foreach ($artifact->replacements as $r) {
            $byValue[$r->needle] = $r->replacement;
        }
        $this->assertSame('Status::New', $byValue["'new'"]);
        $this->assertSame('Status::Active', $byValue["'active'"]);
        $this->assertSame('Status::Closed', $byValue["'closed'"]);
    }

    public function testCaseNamesArePascalCased(): void
    {
        $target = $this->target();
        $artifact = $target->build($this->closedGroup(['role:admin', 'role-user']));

        $enumSource = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString("case RoleAdmin = 'role:admin';", $enumSource);
        $this->assertStringContainsString("case RoleUser = 'role-user';", $enumSource);
    }

    private function candidateWithValues(array $values): Candidate
    {
        $occurrences = [];
        $line = 10;
        foreach ($values as $value) {
            $occurrences[] = new StringLiteral(
                value: $value,
                line: $line++,
                role: LiteralRole::Unclassified,
                className: 'StatusMachine',
                methodName: 'label',
                raw: "'" . $value . "'",
                namingContext: new NamingContext(variableName: 'status'),
            );
        }

        return new Candidate($values[0], 'STATUS', $occurrences);
    }

    public function testBuildEmptyOccurrencesReturnsNoop(): void
    {
        $artifact = $this->target()->build(new GroupedCandidate(new Candidate('x', 'STATUS', [])));

        $this->assertSame(TargetKind::Enum, $artifact->kind);
        $this->assertSame([], $artifact->insertions);
        $this->assertSame([], $artifact->replacements);
    }

    public function testDuplicateValueSkipsSecondCase(): void
    {
        $artifact = $this->target()->build(new GroupedCandidate($this->candidateWithValues(['new', 'new'])));

        $enumSource = implode("\n", $artifact->insertions[0]->lines);
        $this->assertSame(1, substr_count($enumSource, "= 'new';"));
    }

    public function testCaseNameCollisionGetsNumericSuffix(): void
    {
        // 'foo-bar' and 'foo_bar' both slugify to 'FooBar' → second gets a suffix.
        $artifact = $this->target()->build(new GroupedCandidate($this->candidateWithValues(['foo-bar', 'foo_bar'])));

        $enumSource = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString("case FooBar = 'foo-bar';", $enumSource);
        $this->assertStringContainsString("case FooBar2 = 'foo_bar';", $enumSource);
    }

    public function testEnumNameDefaultsToDisenchantedWhenProposedEmpty(): void
    {
        $group = new GroupedCandidate($this->candidateWithValues(['a', 'b']), true, null);
        $artifact = $this->target()->build($group);

        $enumSource = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString('enum Disenchanted: string {', $enumSource);
    }
}
