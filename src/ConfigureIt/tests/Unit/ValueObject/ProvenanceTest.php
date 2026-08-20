<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

class ProvenanceTest extends Unit
{
    private function provenance(): Provenance
    {
        return new Provenance(
            resourceUri: 'config/app.yaml',
            format: Format::YAML,
            adapter: 'ItPack\ConfigureIt\Adapter\YamlLoader',
            priority: 10,
            sourceKind: 'file',
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

    public function testImmutableReturnsNewInstance(): void
    {
        $base = $this->provenance();
        $next = $base->withProcessor('CastingProcessor');

        verify($next)->notSame($base);
        verify($base->processorTrail())->equals([]);
    }
}
