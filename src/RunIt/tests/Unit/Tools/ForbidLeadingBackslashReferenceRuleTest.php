<?php

declare(strict_types=1);

namespace Tests\Unit\Tools;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tools\PhpStan\RunitRules\Rule\ForbidLeadingBackslashReferenceRule;

/**
 * @extends RuleTestCase<ForbidLeadingBackslashReferenceRule>
 */
#[CoversClass(ForbidLeadingBackslashReferenceRule::class)]
final class ForbidLeadingBackslashReferenceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ForbidLeadingBackslashReferenceRule(dirname(__DIR__, 2) . '/Support/Fixture/LeadingBackslash');
    }

    public function testTraitAndClassReferences(): void
    {
        require_once __DIR__ . '/../../Support/Fixture/LeadingBackslash/ExampleTrait.inc';
        require_once __DIR__ . '/../../Support/Fixture/LeadingBackslash/Consumer.inc';

        $message = 'Leading-backslash reference "DateTimeImmutable" must be imported via a `use` statement '
            . 'instead of being inlined (RunIt T2.2).';

        $this->analyse([
            __DIR__ . '/../../Support/Fixture/LeadingBackslash/ExampleTrait.inc',
            __DIR__ . '/../../Support/Fixture/LeadingBackslash/Consumer.inc',
        ], [
            [$message, 16],
            [$message, 18],
            [$message, 18],
            [$message, 20],
        ]);
    }
}
