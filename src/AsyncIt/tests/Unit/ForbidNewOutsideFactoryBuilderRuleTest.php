<?php

declare(strict_types=1);

namespace Tests\Unit;

use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\RuleFixture\UserlandInternal;
use Nyholm\Psr7\Request;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use RuntimeException;
use Tools\PhpStan\AsyncItRules\Rule\ForbidNewOutsideFactoryBuilderRule;

/** @extends RuleTestCase<ForbidNewOutsideFactoryBuilderRule> */
final class ForbidNewOutsideFactoryBuilderRuleTest extends RuleTestCase
{
    public function testOnlyInternalClassesAreExempt(): void
    {
        $message = 'Direct construction of %s outside a Factory/Builder namespace is forbidden; use an injected factory (AsyncIt M1).';

        $this->analyse([__DIR__ . '/../Support/Data/ForbidNewOutsideFactoryBuilder.php'], [
            [sprintf($message, FiberFactory::class), 16],
            [sprintf($message, Request::class), 17],
            [sprintf($message, 'ItPack\\AsyncIt\\RuleFixture\\MissingClass'), 18],
            [sprintf($message, UserlandInternal::class), 19],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureRoot = realpath(__DIR__ . '/../Support/Data');
        if ($fixtureRoot === false) {
            throw new RuntimeException('Fixture directory not found.');
        }

        return new ForbidNewOutsideFactoryBuilderRule(
            self::getContainer()->getByType(ReflectionProvider::class),
            srcRoot: $fixtureRoot,
        );
    }
}
