<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Context\ContextClassification;
use ItPack\RunIt\Domain\Context\DefaultContextPropagationPolicy;
use ItPack\RunIt\Domain\Context\DefaultContextSerializationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DefaultContextPropagationPolicy::class)]
#[CoversClass(DefaultContextSerializationPolicy::class)]
#[UsesClass(ContextClassification::class)]
final class DefaultContextPoliciesTest extends Unit
{
    public function testPropagationDelegatesToClassification(): void
    {
        $policy = new DefaultContextPropagationPolicy();

        self::assertTrue($policy->isPropagatable(ContextClassification::Public));
        self::assertTrue($policy->isPropagatable(ContextClassification::Internal));
        self::assertFalse($policy->isPropagatable(ContextClassification::Confidential));
        self::assertFalse($policy->isPropagatable(ContextClassification::Secret));
    }

    public function testSerializationDelegatesToClassification(): void
    {
        $policy = new DefaultContextSerializationPolicy();

        self::assertTrue($policy->isSerializable(ContextClassification::Public));
        self::assertTrue($policy->isSerializable(ContextClassification::Restricted));
        self::assertFalse($policy->isSerializable(ContextClassification::Secret));
    }
}
