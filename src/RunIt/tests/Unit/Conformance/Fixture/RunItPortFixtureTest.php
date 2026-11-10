<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Fixture;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;

/**
 * @group conformance
 */
final class RunItPortFixtureTest extends Unit
{
    public function testSafeObservation(): void
    {
        $fixture = new RunItPortFixture();

        $observation = $fixture->safeObservation();

        self::assertSame('Service started', $observation->message());
        self::assertSame('bootstrap', $observation->attributes()['component']);
    }

    public function testSensitiveObservationContainsSecret(): void
    {
        $fixture = new RunItPortFixture();

        self::assertSame('hunter2', $fixture->sensitiveAttributeValue());
        self::assertSame('Connected to upstream', $fixture->sensitiveObservation()->message());
    }

    public function testMeasurement(): void
    {
        $fixture = new RunItPortFixture();

        $measurement = $fixture->measurement();

        self::assertSame('items.processed', $measurement->name()->toString());
        self::assertSame(3, $measurement->value());
    }

    public function testOccurrence(): void
    {
        $fixture = new RunItPortFixture();

        $occurrence = $fixture->occurrence();

        self::assertSame('system.boot.v1', $occurrence->type()->toString());
        self::assertSame('runit.runtime', $occurrence->source());
    }

    public function testSnapshot(): void
    {
        $fixture = new RunItPortFixture();

        $snapshot = $fixture->snapshot();

        self::assertSame('configuration.snapshot', $snapshot->type()->toString());
        self::assertSame(1, $snapshot->schemaVersion());
    }

    public function testSnapshotPatcherFlipsBetaFlag(): void
    {
        $fixture = new RunItPortFixture();

        $patched = ($fixture->snapshotPatcher())($fixture->snapshot());

        self::assertSame(['feature_flags' => ['beta' => false]], $patched->payload());
    }

    public function testStringIdentifierValidator(): void
    {
        $fixture = new RunItPortFixture();

        self::assertSame(StringIdentifierValidator::class, $fixture->stringIdentifierValidator()::class);
    }

    public function testSamplingArtifacts(): void
    {
        $fixture = new RunItPortFixture();

        $artifacts = $fixture->samplingArtifacts();

        self::assertCount(4, $artifacts);
    }
}
