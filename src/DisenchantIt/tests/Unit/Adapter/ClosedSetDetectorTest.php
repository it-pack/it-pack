<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\ClosedSetDetector;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NamingContext;
use ItPack\DisenchantIt\ValueObject\StringLiteral;

final class ClosedSetDetectorTest extends Unit
{
    private ClosedSetDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ClosedSetDetector();
    }

    /**
     * @param list<string> $values
     */
    private function candidate(string $variableName, array $values): Candidate
    {
        $occurrences = [];
        $line = 10;
        foreach ($values as $value) {
            $occurrences[] = new StringLiteral(
                value: $value,
                line: $line++,
                role: LiteralRole::Unclassified,
                className: 'C',
                methodName: 'f',
                raw: "'" . $value . "'",
                namingContext: new NamingContext(variableName: $variableName),
            );
        }

        return new Candidate($values[0], 'NAME', $occurrences);
    }

    private function detect(Candidate $candidate): bool
    {
        $groups = $this->detector->detect(['k' => $candidate]);

        return $groups['k']->isClosedSet;
    }

    public function testClosedSetWhenSharedVariableAndSeveralDistinctValues(): void
    {
        $group = $this->detector->detect(['k' => $this->candidate('status', ['new', 'active', 'closed'])])['ctx:status'];

        $this->assertTrue($group->isClosedSet);
        $this->assertSame('status', $group->proposedEnumName);
    }

    public function testSingleOccurrenceIsNotClosed(): void
    {
        $this->assertFalse($this->detect($this->candidate('status', ['only'])));
    }

    public function testSameVariableSingleDistinctValueIsNotClosed(): void
    {
        // Repeated identical value (one distinct value) is not a stable enum set.
        $this->assertFalse($this->detect($this->candidate('status', ['new', 'new', 'new'])));
    }

    public function testDifferentVariablesAreNotClosed(): void
    {
        $occurrences = [
            new StringLiteral(value: 'a', line: 1, role: LiteralRole::Unclassified, className: 'C', methodName: 'f', raw: "'a'", namingContext: new NamingContext(variableName: 'first')),
            new StringLiteral(value: 'b', line: 2, role: LiteralRole::Unclassified, className: 'C', methodName: 'f', raw: "'b'", namingContext: new NamingContext(variableName: 'second')),
        ];

        $this->assertFalse($this->detect(new Candidate('a', 'NAME', $occurrences)));
    }

    public function testNoNamingContextIsNotClosed(): void
    {
        // e.g. literals returned from a function — no variable to anchor to.
        $occurrences = [
            new StringLiteral(value: 'x', line: 1, role: LiteralRole::Unclassified, className: 'C', methodName: 'f', raw: "'x'", namingContext: null),
            new StringLiteral(value: 'y', line: 2, role: LiteralRole::Unclassified, className: 'C', methodName: 'f', raw: "'y'", namingContext: null),
        ];

        $this->assertFalse($this->detect(new Candidate('x', 'NAME', $occurrences)));
    }

    public function testMixedKindsAreNotClosed(): void
    {
        $occurrences = [
            new StringLiteral(value: 'a', line: 1, role: LiteralRole::Unclassified, className: 'C', methodName: 'f', raw: "'a'", namingContext: new NamingContext(variableName: 'v')),
            new \ItPack\DisenchantIt\ValueObject\NumberLiteral(kind: LiteralKind::Int->value, value: '1', raw: '1', line: 2, role: LiteralRole::NumericExpression, className: 'C', methodName: 'f', namingContext: new NamingContext(variableName: 'v')),
        ];

        $this->assertFalse($this->detect(new Candidate('a', 'NAME', $occurrences)));
    }
}
