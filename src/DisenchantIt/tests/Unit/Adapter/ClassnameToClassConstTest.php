<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Rewriting\ClassnameToClassConst;
use ItPack\DisenchantIt\ValueObject\Candidate\Candidate;
use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

final class ClassnameToClassConstTest extends Unit
{
    private function lit(string $value, bool $isClassname, int $line): StringLiteral
    {
        return new StringLiteral(
            value: $value,
            line: $line,
            role: LiteralRole::Unclassified,
            className: 'C',
            methodName: 'f',
            raw: "'" . $value . "'",
            isClassnameString: $isClassname,
        );
    }

    private function group(StringLiteral ...$lits): GroupedCandidate
    {
        return new GroupedCandidate(new Candidate($lits[0]->value, 'NAME', $lits));
    }

    public function testSupportsTrueForClassnameStrings(): void
    {
        $group = $this->group($this->lit('App\\Foo\\Bar', true, 5));

        $this->assertTrue(new ClassnameToClassConst()->supports($group));
    }

    public function testSupportsFalseWhenEmpty(): void
    {
        $group = new GroupedCandidate(new Candidate('x', 'NAME', []));

        $this->assertFalse(new ClassnameToClassConst()->supports($group));
    }

    public function testSupportsFalseWhenNonClassname(): void
    {
        $group = $this->group($this->lit('not a class', false, 5));

        $this->assertFalse(new ClassnameToClassConst()->supports($group));
    }

    public function testBuildRewritesToShortClassConst(): void
    {
        $group = $this->group($this->lit('App\\Foo\\Bar', true, 5));
        $artifact = new ClassnameToClassConst()->build($group);

        $this->assertSame(TargetKind::Fqcn, $artifact->kind);

        $byNeedle = [];
        foreach ($artifact->replacements as $r) {
            $byNeedle[$r->needle] = $r->replacement;
        }
        $this->assertSame('Bar::class', $byNeedle["'App\\Foo\\Bar'"]);
    }

    public function testBuildEmptyReturnsNoop(): void
    {
        $group = new GroupedCandidate(new Candidate('x', 'NAME', []));
        $artifact = new ClassnameToClassConst()->build($group);

        $this->assertSame(TargetKind::Fqcn, $artifact->kind);
        $this->assertSame([], $artifact->replacements);
    }
}
