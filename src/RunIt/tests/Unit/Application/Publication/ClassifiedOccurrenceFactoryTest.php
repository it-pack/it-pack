<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ClassifiedOccurrenceFactory;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;

#[CoversClass(ClassifiedOccurrenceFactory::class)]
final class ClassifiedOccurrenceFactoryTest extends Unit
{
    public function testCreateWithValidFieldsReturnsOccurrence(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.custom.v1'),
            'test-source',
            Instant::of(1000),
            [],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial()
        );
        $fields = $occurrence->normalize();

        $result = $factory->create($fields);

        self::assertInstanceOf(OccurrenceInterface::class, $result);
        self::assertSame('occ-1', $result->id()->toString());
        self::assertSame('exec-1', $result->executionId()->toString());
        self::assertSame('itpack.custom.v1', $result->type()->toString());
    }

    public function testCreateWithValidActivityIdReturnsOccurrence(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.custom.v1'),
            'test-source',
            Instant::of(1000),
            [],
            ActivityId::fromString('act-1'),
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial()
        );
        $fields = $occurrence->normalize();

        $result = $factory->create($fields);

        self::assertInstanceOf(OccurrenceInterface::class, $result);
        self::assertSame('act-1', $result->activityId()?->toString());
    }

    public function testCreateWithMissingKeyReturnsNull(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $fields = [
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'test-source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 1,
            'activity_id' => null,
            'subject' => null,
            'data_schema' => null,
        ];

        self::assertNull($factory->create($fields));
    }

    public function testCreateWithInvalidFieldTypesReturnsNull(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $fields = [
            'id' => 123,
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'test-source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 1,
            'activity_id' => null,
            'subject' => null,
            'data_schema' => null,
            'version' => '1.0',
        ];

        self::assertNull($factory->create($fields));
    }

    public function testCreateWithNonStringActivityIdReturnsNull(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $fields = [
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'test-source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 1,
            'activity_id' => 123,
            'subject' => null,
            'data_schema' => null,
            'version' => '1.0',
        ];

        self::assertNull($factory->create($fields));
    }

    public function testCreateWithNormalizedMismatchReturnsNull(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $fields = [
            'id' => 'occ-1',
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'test-source',
            'time' => 1000,
            'data' => ['key' => 'value'],
            'content_type' => 'application/json',
            'extension_attributes' => ['ext' => 'val'],
            'sequence' => 99,
            'activity_id' => null,
            'subject' => null,
            'data_schema' => null,
            'version' => '1.0',
        ];

        self::assertNull($factory->create($fields));
    }

    public function testCreateWithOccurrenceCreationExceptionReturnsNull(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $fields = [
            'id' => "\0invalid",
            'execution_id' => 'exec-1',
            'type' => 'itpack.custom.v1',
            'source' => 'test-source',
            'time' => 1000,
            'data' => [],
            'content_type' => 'application/json',
            'extension_attributes' => [],
            'sequence' => 1,
            'activity_id' => null,
            'subject' => null,
            'data_schema' => null,
            'version' => '1.0',
        ];

        self::assertNull($factory->create($fields));
    }

    /**
     * @throws ReflectionException
     */
    public function testTypedExtensionAttributesDropsNonStringKeys(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $ref = new ReflectionMethod(ClassifiedOccurrenceFactory::class, 'typedExtensionAttributes');

        $result = $ref->invoke($factory, [0 => 'dropped', 'valid' => 'kept', '1.5' => 'string_key_kept']);

        self::assertSame(['valid' => 'kept', '1.5' => 'string_key_kept'], $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testTypedExtensionAttributesDropsNonScalarNonNullValues(): void
    {
        $factory = new ClassifiedOccurrenceFactory();
        $ref = new ReflectionMethod(ClassifiedOccurrenceFactory::class, 'typedExtensionAttributes');

        $result = $ref->invoke($factory, [
            'scalar' => 'value',
            'null' => null,
            'array' => ['nested'],
            'object' => new stdClass(),
        ]);

        self::assertSame(['scalar' => 'value', 'null' => null], $result);
    }
}
