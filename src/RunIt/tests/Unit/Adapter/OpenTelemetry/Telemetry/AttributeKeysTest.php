<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ReflectionClass;

/**
 * Wire-contract verification: every AttributeKeys constant must match its
 * literal OpenTelemetry attribute spelling. Changing a constant value here
 * changes the exported wire format.
 */
final class AttributeKeysTest extends Unit
{
    public function testConstantsMatchTheirLiteralSpellings(): void
    {
        $reflection = new ReflectionClass(AttributeKeys::class);
        $constants = $reflection->getConstants();

        self::assertSame('runit.execution.id', $constants['EXECUTION_ID']);
        self::assertSame('runit.execution.name', $constants['EXECUTION_NAME']);
        self::assertSame('runit.execution.kind', $constants['EXECUTION_KIND']);
        self::assertSame('runit.execution.parent_id', $constants['EXECUTION_PARENT_ID']);
        self::assertSame('runit.execution.status', $constants['EXECUTION_STATUS']);

        self::assertSame('runit.activity.id', $constants['ACTIVITY_ID']);
        self::assertSame('runit.activity.name', $constants['ACTIVITY_NAME']);
        self::assertSame('runit.activity.kind', $constants['ACTIVITY_KIND']);
        self::assertSame('runit.activity.status', $constants['ACTIVITY_STATUS']);
        self::assertSame('runit.activity.parent_id', $constants['ACTIVITY_PARENT_ID']);

        self::assertSame('runit.observation.id', $constants['OBSERVATION_ID']);
        self::assertSame('runit.diagnostic.code', $constants['DIAGNOSTIC_CODE']);

        self::assertSame('runit.occurrence.id', $constants['OCCURRENCE_ID']);
        self::assertSame('runit.occurrence.type', $constants['OCCURRENCE_TYPE']);
        self::assertSame('runit.occurrence.source', $constants['OCCURRENCE_SOURCE']);
        self::assertSame('runit.occurrence.subject', $constants['OCCURRENCE_SUBJECT']);
        self::assertSame('runit.occurrence.content_type', $constants['OCCURRENCE_CONTENT_TYPE']);

        self::assertSame('runit.snapshot.id', $constants['SNAPSHOT_ID']);
        self::assertSame('runit.snapshot.type', $constants['SNAPSHOT_TYPE']);
        self::assertSame('runit.snapshot.classification', $constants['SNAPSHOT_CLASSIFICATION']);
        self::assertSame('runit.snapshot.hash', $constants['SNAPSHOT_HASH']);
        self::assertSame('runit.snapshot.schema_version', $constants['SNAPSHOT_SCHEMA_VERSION']);
        self::assertSame('runit.snapshot.content_type', $constants['SNAPSHOT_CONTENT_TYPE']);
        self::assertSame('runit.snapshot.payload', $constants['SNAPSHOT_PAYLOAD']);

        self::assertSame('runit.redacted', $constants['REDACTED']);
    }
}
