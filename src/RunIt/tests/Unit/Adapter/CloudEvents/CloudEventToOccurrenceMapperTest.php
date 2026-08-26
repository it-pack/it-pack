<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultCloudEventToOccurrenceMapper;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M10.3 — CloudEvent to occurrence mapping, validation, and round trip.
 */
final class CloudEventToOccurrenceMapperTest extends Unit
{
    private DefaultCloudEventToOccurrenceMapper $mapper;

    protected function _before(): void
    {
        $this->mapper = new DefaultCloudEventToOccurrenceMapper();
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function eventWith(array $extensions, ?string $time = '2026-08-20T14:05:47Z'): CloudEvent
    {
        return CloudEvent::create(
            'occ-' . md5((string) json_encode($extensions)),
            'it-pack/test',
            'itpack.configuration.loaded.v1',
            ['outcome' => 'ok'],
            'subject-1',
            $time,
            'application/json',
            null,
            $extensions + [
                CloudEventExtensionKey::ExecutionId->value => 'exec-1',
                'tenantflag' => 'acme',
            ],
            '1.0',
        );
    }

    private function event(): CloudEvent
    {
        return CloudEvent::create(
            'occ-1',
            'it-pack/test',
            'itpack.configuration.loaded.v1',
            ['outcome' => 'ok'],
            'subject-1',
            '2026-08-20T14:05:47Z',
            'application/json',
            'https://schema.example/config',
            [
                CloudEventExtensionKey::ExecutionId->value => 'exec-1',
                CloudEventExtensionKey::ActivityId->value => 'act-1',
                CloudEventExtensionKey::Sequence->value => 4,
                CloudEventExtensionKey::CorrelationId->value => 'corr-9',
                'tenantflag' => 'acme',
            ],
        );
    }

    public function testReconstructsSemanticFields(): void
    {
        $occurrence = $this->mapper->map($this->event());

        self::assertSame('occ-1', $occurrence->id()->toString());
        self::assertSame('itpack.configuration.loaded.v1', $occurrence->type()->toString());
        self::assertSame(
            OccurrenceType::fromString('itpack.configuration.loaded.v1')->toString(),
            $occurrence->type()->toString()
        );
        self::assertSame('it-pack/test', $occurrence->source());
        self::assertSame('subject-1', $occurrence->subject());
        self::assertSame('exec-1', $occurrence->executionId()->toString());
        self::assertSame('act-1', $occurrence->activityId()?->toString());
        self::assertSame(4, $occurrence->sequence());
        self::assertSame(['outcome' => 'ok'], $occurrence->data());
        self::assertSame('application/json', $occurrence->contentType());
        self::assertSame('https://schema.example/config', $occurrence->dataSchema());
    }

    public function testStringSequenceValuesAreCoercedAndMissingSequencesStartAtInitial(): void
    {
        $withSequence = $this->eventWith(['sequence' => '7']);
        self::assertSame(7, $this->mapper->map($withSequence)->sequence());

        $withoutSequence = $this->eventWith([]);
        self::assertSame(0, $this->mapper->map($withoutSequence)->sequence());
    }

    public function testMissingTimeFallsBackToNowAndMalformedTimeIsRejected(): void
    {
        $noTime = $this->eventWith([], time: null);
        $mapped = $this->mapper->map($noTime);
        self::assertInstanceOf(InstantInterface::class, $mapped->time());

        $this->expectException(InvalidCloudEventException::class);
        $this->mapper->map($this->eventWith([], time: 'definitely-not-a-timestamp'));
    }

    public function testPreservesUnknownExtensionsAndCorrelation(): void
    {
        $occurrence = $this->mapper->map($this->event());

        $attributes = $occurrence->extensionAttributes();
        self::assertSame('acme', $attributes['tenantflag']);
        self::assertSame('corr-9', $attributes['correlation.id']);
        self::assertArrayNotHasKey(CloudEventExtensionKey::ExecutionId->value, $attributes);
    }

    public function testRejectsEventWithoutExecutionIdentity(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $this->mapper->map(CloudEvent::create('occ-1', '/source', 'itpack.thing.happened.v1'));
    }

    public function testRoundTripPreservesSupportedSemantics(): void
    {
        $forward = new DefaultOccurrenceToCloudEventMapper();
        $occurrence = RunItArtifactFactory::occurrence(
            'occ-rt',
            'exec-rt',
            'itpack.job.finished.v1',
            ['result' => 'done'],
            'act-rt',
        );

        $event = $forward->map($occurrence);
        $reconstructed = $this->mapper->map($event);

        self::assertSame($occurrence->id()->toString(), $reconstructed->id()->toString());
        self::assertSame($occurrence->type()->toString(), $reconstructed->type()->toString());
        self::assertSame($occurrence->executionId()->toString(), $reconstructed->executionId()->toString());
        self::assertSame($occurrence->activityId()?->toString(), $reconstructed->activityId()?->toString());
        self::assertSame($occurrence->data(), $reconstructed->data());
    }
}
