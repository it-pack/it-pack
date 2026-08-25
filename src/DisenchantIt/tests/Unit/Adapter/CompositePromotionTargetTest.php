<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\CompositePromotionTarget;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\NameSlugifier;
use ItPack\DisenchantIt\Factory\ConstantPlacementFactory;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NamingContext;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use ItPack\DisenchantIt\ValueObject\TargetKind;

final class CompositePromotionTargetTest extends Unit
{
    private function composite(): CompositePromotionTarget
    {
        return new CompositePromotionTarget([
            new BackedEnumTarget(new NameSlugifier()),
            new ClassConstantTarget(ConstantPlacementFactory::create(), ConstantNameResolver::create()),
        ]);
    }

    private function stringCandidate(string $value, string $variableName): Candidate
    {
        return new Candidate($value, 'NAME', [
            new StringLiteral(
                value: $value,
                line: 5,
                role: LiteralRole::Unclassified,
                className: 'C',
                methodName: 'f',
                raw: "'" . $value . "'",
                namingContext: new NamingContext(variableName: $variableName),
            ),
        ]);
    }

    public function testPrefersBackedEnumForClosedSets(): void
    {
        $composite = $this->composite();
        $group = new GroupedCandidate(
            $this->stringCandidate('new', 'status'),
            true,
            'status',
        );

        $this->assertTrue($composite->supports($group));
        $this->assertSame(TargetKind::Enum, $composite->build($group)->kind);
    }

    public function testFallsBackToClassConstantOtherwise(): void
    {
        $composite = $this->composite();
        $group = new GroupedCandidate($this->stringCandidate('admin-user', 'status'));

        $this->assertTrue($composite->supports($group));
        $this->assertSame(TargetKind::Const, $composite->build($group)->kind);
    }

    public function testUnsupportedWhenNoTargetApplies(): void
    {
        $composite = new CompositePromotionTarget([]);
        $group = new GroupedCandidate($this->stringCandidate('x', 'v'));

        $this->assertFalse($composite->supports($group));
    }

    public function testBuildWithNoSupportingTargetReturnsConstArtifact(): void
    {
        $composite = new CompositePromotionTarget([]);
        $group = new GroupedCandidate($this->stringCandidate('x', 'v'));

        $artifact = $composite->build($group);

        $this->assertSame(TargetKind::Const, $artifact->kind);
        $this->assertSame([], $artifact->insertions);
        $this->assertSame([], $artifact->replacements);
    }
}
