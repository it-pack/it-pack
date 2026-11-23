<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ReflectionClass;

/**
 * Wire-contract verification: every ArtifactField constant must match its
 * literal normalized projection key. Changing a constant value here changes
 * the exported artifact projection and silently breaks redaction.
 */
final class ArtifactFieldTest extends Unit
{
    public function testConstantsMatchTheirLiteralValues(): void
    {
        $reflection = new ReflectionClass(ArtifactField::class);
        $constants = $reflection->getConstants();

        self::assertSame('id', $constants['ID']);
        self::assertSame('context', $constants['CONTEXT']);
        self::assertSame('attributes', $constants['ATTRIBUTES']);
        self::assertSame('exception', $constants['EXCEPTION']);
        self::assertSame('throwable', $constants['THROWABLE']);
        self::assertSame('data', $constants['DATA']);
        self::assertSame('payload', $constants['PAYLOAD']);
        self::assertSame('message', $constants['MESSAGE']);
        self::assertSame('correlation', $constants['CORRELATION']);

        self::assertSame('execution_id', $constants['EXECUTION_ID']);
        self::assertSame('activity_id', $constants['ACTIVITY_ID']);
        self::assertSame('observation_id', $constants['OBSERVATION_ID']);
        self::assertSame('parent_execution_id', $constants['PARENT_EXECUTION_ID']);
        self::assertSame('linked_execution_ids', $constants['LINKED_EXECUTION_IDS']);
        self::assertSame('parent_activity_id', $constants['PARENT_ACTIVITY_ID']);
        self::assertSame('predecessor_id', $constants['PREDECESSOR_ID']);

        self::assertSame('name', $constants['NAME']);
        self::assertSame('kind', $constants['KIND']);
        self::assertSame('status', $constants['STATUS']);
        self::assertSame('result', $constants['RESULT']);
        self::assertSame('sequence', $constants['SEQUENCE']);
        self::assertSame('started_at', $constants['STARTED_AT']);
        self::assertSame('ended_at', $constants['ENDED_AT']);
        self::assertSame('created_at', $constants['CREATED_AT']);
        self::assertSame('timestamp', $constants['TIMESTAMP']);
        self::assertSame('time', $constants['TIME']);
        self::assertSame('expires_at', $constants['EXPIRES_AT']);
        self::assertSame('duration_ns', $constants['DURATION_NS']);

        self::assertSame('severity', $constants['SEVERITY']);
        self::assertSame('level', $constants['LEVEL']);
        self::assertSame('diagnostic_code', $constants['DIAGNOSTIC_CODE']);

        self::assertSame('unit', $constants['UNIT']);
        self::assertSame('value', $constants['VALUE']);

        self::assertSame('type', $constants['TYPE']);
        self::assertSame('version', $constants['VERSION']);
        self::assertSame('source', $constants['SOURCE']);
        self::assertSame('subject', $constants['SUBJECT']);
        self::assertSame('data_schema', $constants['DATA_SCHEMA']);
        self::assertSame('content_type', $constants['CONTENT_TYPE']);
        self::assertSame('extension_attributes', $constants['EXTENSION_ATTRIBUTES']);
        self::assertSame('classification', $constants['CLASSIFICATION']);
        self::assertSame('schema_version', $constants['SCHEMA_VERSION']);
        self::assertSame('content_hash', $constants['CONTENT_HASH']);

        self::assertSame('key', $constants['KEY']);

        self::assertSame('class', $constants['THROWABLE_CLASS']);
        self::assertSame('code', $constants['CODE']);
        self::assertSame('previous', $constants['PREVIOUS']);
        self::assertSame('trace', $constants['TRACE']);
    }
}
