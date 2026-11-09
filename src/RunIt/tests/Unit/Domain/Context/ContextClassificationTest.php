<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ContextClassificationInterface;
use ItPack\RunIt\Domain\Context\ContextClassification;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ContextClassification::class)]
final class ContextClassificationTest extends Unit
{
    public function testPublicIsExportablePropagatableAndSerializable(): void
    {
        self::assertSame('public', ContextClassification::Public->label());
        self::assertTrue(ContextClassification::Public->isExportable());
        self::assertTrue(ContextClassification::Public->isPropagatable());
        self::assertTrue(ContextClassification::Public->isSerializable());
    }

    public function testInternalIsNotExportableButPropagatable(): void
    {
        self::assertFalse(ContextClassification::Internal->isExportable());
        self::assertTrue(ContextClassification::Internal->isPropagatable());
        self::assertTrue(ContextClassification::Internal->isSerializable());
    }

    public function testConfidentialIsNeitherExportableNorPropagatable(): void
    {
        self::assertFalse(ContextClassification::Confidential->isExportable());
        self::assertFalse(ContextClassification::Confidential->isPropagatable());
        self::assertTrue(ContextClassification::Confidential->isSerializable());
    }

    public function testRestrictedIsMaskedButSerializable(): void
    {
        self::assertFalse(ContextClassification::Restricted->isExportable());
        self::assertFalse(ContextClassification::Restricted->isPropagatable());
        self::assertTrue(ContextClassification::Restricted->isSerializable());
    }

    public function testSecretIsDeniedEverywhere(): void
    {
        self::assertFalse(ContextClassification::Secret->isExportable());
        self::assertFalse(ContextClassification::Secret->isPropagatable());
        self::assertFalse(ContextClassification::Secret->isSerializable());
    }

    public function testEachCaseImplementsTheContract(): void
    {
        self::assertInstanceOf(ContextClassificationInterface::class, ContextClassification::Public);
        self::assertInstanceOf(ContextClassificationInterface::class, ContextClassification::Secret);
    }
}
