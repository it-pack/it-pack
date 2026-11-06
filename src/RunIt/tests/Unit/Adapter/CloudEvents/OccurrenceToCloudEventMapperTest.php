<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M10.2 — occurrence to CloudEvent mapping.
 */
final class OccurrenceToCloudEventMapperTest extends Unit
{
    private DefaultOccurrenceToCloudEventMapper $mapper;

    protected function _before(): void
    {
        $this->mapper = new DefaultOccurrenceToCloudEventMapper();
    }

    public function testMapsCoreFields(): void
    {
        $occurrence = RunItArtifactFactory::occurrence(
            'occ-1',
            'exec-1',
            'itpack.configuration.loaded.v1',
            ['outcome' => 'ok'],
        );

        $event = $this->mapper->map($occurrence);

        self::assertSame('occ-1', $event->id);
        self::assertSame('itpack.configuration.loaded.v1', $event->type);
        self::assertSame('it-pack/test', $event->source);
        self::assertSame(['outcome' => 'ok'], $event->data);
        self::assertSame('1.0', $event->specVersion);
        self::assertSame(gmdate('Y-m-d\TH:i:s\Z', 1_600), $event->time);
    }

    public function testMapsRunItCorrelationAsExtensions(): void
    {
        $occurrence = RunItArtifactFactory::occurrence(
            'occ-1',
            'exec-1',
            'itpack.configuration.loaded.v1',
            ['outcome' => 'ok'],
            'act-1',
        );

        $event = $this->mapper->map($occurrence);

        self::assertSame('exec-1', $event->extensions[CloudEventExtensionKey::ExecutionId->value]);
        self::assertSame('act-1', $event->extensions[CloudEventExtensionKey::ActivityId->value]);
        self::assertSame(0, $event->extensions[CloudEventExtensionKey::Sequence->value]);
    }

    public function testMapsCorrelationIdExtensionAndPreservesValidCustomExtensions(): void
    {
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-2'),
            ExecutionId::fromString('exec-2'),
            OccurrenceType::fromString('itpack.job.finished.v1'),
            'it-pack/custom',
            Instant::of(2_000),
            ['result' => 'done'],
            ActivityId::fromString('act-2'),
            contentType: 'application/json',
            extensionAttributes: AttributeCollection::fromArray([
                'correlation.id' => 'corr-9',
                'tenantflag' => 'acme',
                'TenantFlag' => 'ignored',
            ]),
        );

        $event = $this->mapper->map($occurrence);

        self::assertSame('corr-9', $event->extensions[CloudEventExtensionKey::CorrelationId->value]);
        self::assertSame('acme', $event->extensions['tenantflag']);
        self::assertArrayNotHasKey('correlation.id', $event->extensions);
        self::assertArrayNotHasKey('TenantFlag', $event->extensions);
    }

    public function testPolicyControlsSpecVersion(): void
    {
        $occurrence = RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'itpack.configuration.loaded.v1');
        $event = $this->mapper->map($occurrence, new CloudEventMappingPolicy(specVersion: '1.0'));

        self::assertSame('1.0', $event->specVersion);
    }
}
