<?php

declare(strict_types=1);

namespace Tests\Unit\Tools;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tools\PhpStan\RunitRules\Rule\RequireInterfaceRule;

/**
 * @extends RuleTestCase<RequireInterfaceRule>
 */
#[CoversClass(\Tools\PhpStan\RunitRules\Rule\RequireInterfaceRule::class)]
final class RequireInterfaceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireInterfaceRule(
            dirname(__DIR__, 2) . '/Support/Fixture/RequireInterface/src',
            ['src/ExemptDirectory'],
            ['src/Exempt.php'],
            self::getContainer()->getByType(ReflectionProvider::class),
        );
    }

    public function testInheritedInterfacesAndExemptions(): void
    {
        require_once __DIR__ . '/../../Support/Fixture/RequireInterface/src/Classes.php';
        require_once __DIR__ . '/../../Support/Fixture/RequireInterface/src/Exempt.php';
        require_once __DIR__ . '/../../Support/Fixture/RequireInterface/src/ExemptDirectory/Exempt.php';

        $this->analyse([
            __DIR__ . '/../../Support/Fixture/RequireInterface/src/Classes.php',
            __DIR__ . '/../../Support/Fixture/RequireInterface/src/Exempt.php',
            __DIR__ . '/../../Support/Fixture/RequireInterface/src/ExemptDirectory/Exempt.php',
        ], [
            ['Concrete class ParentWithoutInterface must implement an interface (RunIt M13.2).', 27],
            ['Concrete class Missing must implement an interface (RunIt M13.2).', 31],
        ]);
    }
}
