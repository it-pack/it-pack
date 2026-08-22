<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

class ProvenanceTest extends Unit
{
    private function provenance(): Provenance
    {
        return new Provenance(
            resourceUri: 'config/app.yaml',
            format: Format::YAML,
            adapter: 'ItPack\ConfigureIt\Adapter\YamlLoader',
            priority: 10,
            sourceKind: SourceKind::File,
            at: 1.0,
        );
    }

    public function testWithProcessorAppendsAndDedupes(): void
    {
        $p = $this->provenance()->withProcessor('VariableProcessor')->withProcessor('VariableProcessor');

        verify($p->processorTrail())->equals(['VariableProcessor']);
    }

    public function testAsOverriddenFlagsAndRecordsShadowedSource(): void
    {
        $p = $this->provenance()->asOverridden('config/base.yaml');

        verify($p->overridden())->true();
        verify($p->overriddenSource())->equals('config/base.yaml');
    }

    public function testValidatedRecordsStage(): void
    {
        $p = $this->provenance()->validated('NativeConfigurationSchema');

        verify($p->validatedBy())->equals('NativeConfigurationSchema');
    }

    public function testToArrayIsSerializable(): void
    {
        $array = $this->provenance()->asOverridden('config/base.yaml')->toArray();

        verify($array['resourceUri'])->equals('config/app.yaml');
        verify($array['format'])->equals('YAML');
        verify($array['overridden'])->true();
        verify($array['overriddenSource'])->equals('config/base.yaml');
    }

    public function testSourceKindRoundTripsThroughArray(): void
    {
        $p = $this->provenance();
        verify($p->sourceKind())->equals('file');

        $array = $p->toArray();
        verify($array['sourceKind'])->equals('file');

        $restored = Provenance::fromArray($array);
        verify($restored->sourceKind())->equals('file');
    }

    public function testUnknownSourceKindFallsBackToOther(): void
    {
        $restored = Provenance::fromArray(['sourceKind' => 'exotic-source']);

        verify($restored->sourceKind())->equals('__other__');
    }
}
