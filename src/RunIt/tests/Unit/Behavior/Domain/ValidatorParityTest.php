<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Domain;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use stdClass;

/**
 * The context and attribute rule sets share one bounded primitive walk
 * ({@see BoundedPrimitiveValueWalker}). These tests pin their identical
 * accept/reject sets so the shared implementation cannot silently diverge from
 * either public contract.
 */
final class ValidatorParityTest extends Unit
{
    /**
     * @return list<array{string, mixed}>
     */
    public static function acceptedValueProvider(): array
    {
        return [
            ['null', null],
            ['bool', true],
            ['int', 42],
            ['float', 1.5],
            ['string', 'acme'],
            ['list', [1, 'two', 3.0, null]],
            ['map', ['a' => 1, 'b' => 'x']],
            ['nested', ['deep' => ['deeper' => ['leaf' => 'value']]]],
            ['empty array', []],
        ];
    }

    /**
     * @return list<array{string, mixed}>
     */
    public static function rejectedValueProvider(): array
    {
        return [
            ['object', new stdClass()],
            ['closure', static fn (): bool => true],
        ];
    }

    /**
     * @dataProvider acceptedValueProvider
     */
    public function testAcceptedSetsAreIdentical(string $label, mixed $value): void
    {
        $this->assertAccepted(
            static function (ContextValueValidator $validator) use ($value): void {
                $validator->assertSupportedValue($value);
            },
            static function (AttributeValidator $validator) use ($value): void {
                $validator->assertValidAttributes(['key' => $value]);
            },
            $label,
        );
    }

    /**
     * @dataProvider rejectedValueProvider
     */
    public function testRejectedSetsAreIdentical(string $label, mixed $value): void
    {
        $this->assertRejected(
            static function (ContextValueValidator $validator) use ($value): void {
                $validator->assertSupportedValue($value);
            },
            static function (AttributeValidator $validator) use ($value): void {
                $validator->assertValidAttributes(['key' => $value]);
            },
            $label,
        );
    }

    public function testDepthViolationsProduceComparableRejections(): void
    {
        $tooDeep = ['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => ['l7' => ['l8' => ['l9' => 'x']]]]]]]]];

        $this->assertRejected(
            static function (ContextValueValidator $validator) use ($tooDeep): void {
                $validator->assertSupportedValue($tooDeep);
            },
            static function (AttributeValidator $validator) use ($tooDeep): void {
                $validator->assertValidAttributes(['key' => $tooDeep]);
            },
            'depth',
        );
    }

    private function assertAccepted(Closure $contextRule, Closure $attributeRule, string $label): void
    {
        try {
            $contextRule(new ContextValueValidator());
            $attributeRule(new AttributeValidator());

            $this->addToAssertionCount(1);
        } catch (RunItExceptionInterface $exception) {
            self::fail("shape '{$label}' must be accepted by both validators");
        }
    }

    private function assertRejected(Closure $contextRule, Closure $attributeRule, string $label): void
    {
        foreach (
            [
                ContextValueValidator::class => $contextRule,
                AttributeValidator::class => $attributeRule,
            ] as $validatorClass => $rule
        ) {
            $rejected = false;

            try {
                $rule(new $validatorClass());
            } catch (RunItExceptionInterface) {
                $rejected = true;
            }

            self::assertTrue($rejected, "shape '{$label}' must be rejected by {$validatorClass}");
        }
    }
}
