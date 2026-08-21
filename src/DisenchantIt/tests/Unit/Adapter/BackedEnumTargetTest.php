<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
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

    public function testSupportsOnlyClosedSets(): void
    {
        $target = new BackedEnumTarget();

        $this->assertTrue($target->supports($this->closedGroup(['new', 'active', 'closed'])));
        $this->assertFalse($target->supports(new GroupedCandidate($this->closedCandidate(['lonely']))));
    }

    public function testBuildsTopLevelBackedEnumWithCasesAndReferences(): void
    {
        $target = new BackedEnumTarget();
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
        $target = new BackedEnumTarget();
        $artifact = $target->build($this->closedGroup(['role:admin', 'role-user']));

        $enumSource = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString("case RoleAdmin = 'role:admin';", $enumSource);
        $this->assertStringContainsString("case RoleUser = 'role-user';", $enumSource);
    }
}
