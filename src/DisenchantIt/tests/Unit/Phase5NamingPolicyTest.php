<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\Naming\DictionaryNameResolver;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;
use ItPack\DisenchantIt\ValueObject\Naming\NameCasing;
use ItPack\DisenchantIt\ValueObject\Naming\NamePolicy;

final class Phase5NamingPolicyTest extends Unit
{
    private function stringLiteral(string $value): StringLiteral
    {
        return new StringLiteral(
            value: $value,
            line: 1,
            role: LiteralRole::Unclassified,
            className: 'C',
            methodName: 'a',
            kind: LiteralKind::String->value,
        );
    }

    public function testDictionaryOverrideWinsOverGenerator(): void
    {
        $resolver = ConstantNameResolver::create(
            dictionary: DictionaryNameResolver::create(['admin-user' => 'ADMIN_ACCOUNT']),
        );

        $this->assertSame('ADMIN_ACCOUNT', $resolver->resolve($this->stringLiteral('admin-user')));
    }

    public function testUnknownLiteralFallsBackToGenerator(): void
    {
        $resolver = ConstantNameResolver::create(
            dictionary: DictionaryNameResolver::create(['other' => 'OTHER_CONST']),
        );

        $this->assertSame('ADMIN_USER', $resolver->resolve($this->stringLiteral('admin-user')));
    }

    public function testPolicyCasingAppliedToGeneratedName(): void
    {
        $resolver = ConstantNameResolver::create(
            policy: new NamePolicy(casing: NameCasing::LowerSnake),
        );

        $this->assertSame('admin_user', $resolver->resolve($this->stringLiteral('admin-user')));
    }

    public function testPolicyPrefixAndSuffixAndCasingAppliedToDictionaryName(): void
    {
        $resolver = ConstantNameResolver::create(
            policy: new NamePolicy(prefix: 'APP_', suffix: '_C', casing: NameCasing::UpperSnake),
            dictionary: DictionaryNameResolver::create(['admin-user' => 'adminAccount']),
        );

        $this->assertSame('APP_ADMINACCOUNT_C', $resolver->resolve($this->stringLiteral('admin-user')));
    }

    public function testDisenchanterAppliesDictionaryAndPolicyEndToEnd(): void
    {
        $disenchanter = DisenchanterFactory::create(
            namePolicy: new NamePolicy(prefix: 'APP_'),
            dictionary: DictionaryNameResolver::create(['admin-user' => 'ADMIN_ACCOUNT']),
        )->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString("private const string APP_ADMIN_ACCOUNT = 'admin-user';", $rewritten);
        $this->assertStringContainsString('self::APP_ADMIN_ACCOUNT', $rewritten);
    }
}
