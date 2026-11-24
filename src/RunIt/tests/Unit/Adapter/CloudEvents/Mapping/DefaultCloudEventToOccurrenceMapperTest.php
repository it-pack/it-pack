<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Mapping;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultCloudEventToOccurrenceMapper;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultCloudEventToOccurrenceMapper::class)]
final class DefaultCloudEventToOccurrenceMapperTest extends Unit
{
    private DefaultCloudEventToOccurrenceMapper $mapper;

    protected function _before(): void
    {
        $this->mapper = new DefaultCloudEventToOccurrenceMapper();
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function event(array $extensions = [], ?string $time = null): CloudEvent
    {
        return CloudEvent::create(
            'occ-1',
            '/source',
            'com.example.thing.happened.v1',
            ['outcome' => 'ok'],
            'subject-1',
            $time,
            'application/json',
            null,
            $extensions,
        );
    }

    public function testMapWithExecutionIdProducesOccurrence(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
        ]);

        $occurrence = $this->mapper->map($event);

        self::assertInstanceOf(Occurrence::class, $occurrence);
        self::assertSame('occ-1', $occurrence->id()->toString());
        self::assertSame('exec-1', $occurrence->executionId()->toString());
        self::assertSame('com.example.thing.happened.v1', $occurrence->type()->toString());
        self::assertSame('/source', $occurrence->source());
        self::assertSame(['outcome' => 'ok'], $occurrence->data());
        self::assertSame('subject-1', $occurrence->subject());
    }

    public function testMapMissingExecutionIdThrows(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $this->mapper->map($this->event([]));
    }

    public function testMapWithActivityIdAndSequence(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
            CloudEventExtensionKey::ActivityId->value => 'act-1',
            CloudEventExtensionKey::Sequence->value => 7,
        ]);

        $occurrence = $this->mapper->map($event);

        self::assertSame('act-1', (string) $occurrence->activityId());
        self::assertSame(7, $occurrence->sequence());
    }

    public function testMapWithNumericStringSequence(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
            CloudEventExtensionKey::Sequence->value => '12',
        ]);

        $occurrence = $this->mapper->map($event);

        self::assertSame(12, $occurrence->sequence());
    }

    public function testMapWithoutSequenceUsesInitial(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
        ]);

        $occurrence = $this->mapper->map($event);

        self::assertSame(0, $occurrence->sequence());
        self::assertNull($occurrence->activityId());
    }

    public function testMapWithCorrelationIdAndCustomExtension(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
            CloudEventExtensionKey::CorrelationId->value => 'corr-1',
            'custom-attr' => 'custom-value',
        ]);

        $occurrence = $this->mapper->map($event);

        $attributes = $occurrence->extensionAttributes();
        self::assertSame('corr-1', $attributes[CorrelationKeys::CLOUDEVENTS_CORRELATION_ATTRIBUTE]);
        self::assertSame('custom-value', $attributes['custom-attr']);
        self::assertCount(2, $attributes);
    }

    public function testMapWithNullTimeUsesNow(): void
    {
        $event = $this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
        ]);

        $occurrence = $this->mapper->map($event);

        self::assertInstanceOf(Instant::class, $occurrence->time());
        self::assertSame('application/json', $occurrence->contentType());
    }

    public function testMapWithMalformedTimeThrows(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $this->mapper->map($this->event([
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
        ], 'not-a-time'));
    }

    public function testMapResolvesDataContentTypeAndSchema(): void
    {
        $event = CloudEvent::create(
            'occ-1',
            '/source',
            'com.example.v1',
            ['k' => 'v'],
            'subject-1',
            '2026-08-20T14:05:47Z',
            'text/plain',
            'schema-v1',
            [CloudEventExtensionKey::ExecutionId->value => 'exec-1'],
        );

        $occurrence = $this->mapper->map($event);

        self::assertSame('text/plain', $occurrence->contentType());
        self::assertSame('schema-v1', $occurrence->dataSchema());
        self::assertSame('subject-1', $occurrence->subject());
    }
}
