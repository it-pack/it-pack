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

    /**
     * Every mutator is a no-op when the target state is already reached, so a
     * repeatedly-applied pipeline never allocates a redundant provenance.
     */
    public function testMutatorsReturnTheSameInstanceWhenNothingChanges(): void
    {
        $withProcessor = $this->provenance()->withProcessor('VariableProcessor');
        self::assertSame($withProcessor, $withProcessor->withProcessor('VariableProcessor'));

        $overridden = $this->provenance()->asOverridden('config/base.yaml');
        self::assertSame($overridden, $overridden->asOverridden('config/base.yaml'));

        $validated = $this->provenance()->validated('NativeConfigurationSchema');
        self::assertSame($validated, $validated->validated('NativeConfigurationSchema'));

        $sensitive = $this->provenance()->asSensitive();
        self::assertSame($sensitive, $sensitive->asSensitive());
    }

    public function testMutatorsReturnANewInstanceWhenStateChanges(): void
    {
        $base = $this->provenance();

        self::assertNotSame($base, $base->withProcessor('CastingProcessor'));
        self::assertNotSame($base, $base->asOverridden('other.yaml'));
        self::assertNotSame($base, $base->validated('OtherSchema'));
        self::assertNotSame($base, $base->asSensitive());
    }

    public function testWithProcessorPreservesTrailOrder(): void
    {
        $p = $this->provenance()
            ->withProcessor('IncludeProcessor')
            ->withProcessor('VariableProcessor')
            ->withProcessor('IncludeProcessor')
            ->withProcessor('CastingProcessor')
        ;

        verify($p->processorTrail())->equals([
            'IncludeProcessor',
            'VariableProcessor',
            'CastingProcessor',
        ]);
    }

    public function testAsOverriddenWithADifferentShadowedSourceReplacesIt(): void
    {
        $p = $this->provenance()->asOverridden('a.yaml')->asOverridden('b.yaml');

        verify($p->overridden())->true();
        verify($p->overriddenSource())->equals('b.yaml');
    }

    public function testAsOverriddenAcceptsAnUnknownShadowedSource(): void
    {
        $p = $this->provenance()->asOverridden();

        verify($p->overridden())->true();
        verify($p->overriddenSource())->null();
    }

    public function testValidatedByADifferentStageReplacesIt(): void
    {
        $p = $this->provenance()->validated('First')->validated('Second');

        verify($p->validatedBy())->equals('Second');
    }

    public function testAsSensitiveFlagsTheRecord(): void
    {
        verify($this->provenance()->isSensitive())->false();
        verify($this->provenance()->asSensitive()->isSensitive())->true();
        verify($this->provenance()->asSensitive()->toArray()['sensitive'])->true();
    }

    /**
     * The copy() helper must carry every unrelated field across untouched,
     * whichever mutator triggered it.
     */
    public function testCopyPreservesEveryUnrelatedField(): void
    {
        $full = new Provenance(
            resourceUri: 'config/app.yaml',
            format: Format::TOML,
            adapter: 'TomlLoader',
            priority: 42,
            sourceKind: SourceKind::Vault,
            processorTrail: ['IncludeProcessor'],
            overridden: true,
            overriddenSource: 'config/base.yaml',
            validatedBy: 'SchemaX',
            layerId: 'layer-7',
            at: 12.5,
            sensitive: true,
        );

        foreach ([
            $full->withProcessor('CastingProcessor'),
            $full->asOverridden('config/other.yaml'),
            $full->validated('SchemaY'),
        ] as $copy) {
            verify($copy->resourceUri())->equals('config/app.yaml');
            verify($copy->format())->equals(Format::TOML);
            verify($copy->adapter())->equals('TomlLoader');
            verify($copy->priority())->equals(42);
            verify($copy->sourceKind())->equals('vault');
            verify($copy->layerId())->equals('layer-7');
            verify($copy->at())->equals(12.5);
            verify($copy->isSensitive())->true();
        }
    }

    public function testFullRecordRoundTripsThroughArray(): void
    {
        $original = new Provenance(
            resourceUri: 'vault://secret/app',
            format: Format::JSON,
            adapter: 'VaultLoader',
            priority: 90,
            sourceKind: SourceKind::Vault,
            processorTrail: ['IncludeProcessor', 'CastingProcessor'],
            overridden: true,
            overriddenSource: 'config/base.yaml',
            validatedBy: 'NativeConfigurationSchema',
            layerId: 'tenant-a',
            at: 1.75,
            sensitive: true,
        );

        $restored = Provenance::fromArray($original->toArray());

        verify($restored->toArray())->equals($original->toArray());
        verify($restored->processorTrail())->equals(['IncludeProcessor', 'CastingProcessor']);
        verify($restored->overridden())->true();
        verify($restored->overriddenSource())->equals('config/base.yaml');
        verify($restored->validatedBy())->equals('NativeConfigurationSchema');
        verify($restored->layerId())->equals('tenant-a');
        verify($restored->at())->equals(1.75);
        verify($restored->isSensitive())->true();
    }

    public function testFromArrayFallsBackToSafeDefaultsForAnEmptyPayload(): void
    {
        $p = Provenance::fromArray([]);

        verify($p->resourceUri())->equals('');
        verify($p->format())->equals(Format::UNKNOWN);
        verify($p->adapter())->equals('');
        verify($p->priority())->equals(0);
        verify($p->sourceKind())->equals('__other__');
        verify($p->processorTrail())->equals([]);
        verify($p->overridden())->false();
        verify($p->overriddenSource())->null();
        verify($p->validatedBy())->null();
        verify($p->layerId())->null();
        verify($p->at())->equals(0.0);
        verify($p->isSensitive())->false();
    }

    /**
     * A hostile or corrupted cache artifact must never produce a broken record.
     *
     * @param array<array-key, mixed> $payload
     * @param array<string, mixed> $expected
     *
     * @dataProvider malformedPayloads
     */
    public function testFromArrayCoercesMalformedFields(array $payload, array $expected): void
    {
        $restored = Provenance::fromArray($payload);

        foreach ($expected as $accessor => $value) {
            verify($restored->{$accessor}())->equals($value); /* @phpstan-ignore method.dynamicName */
        }
    }

    /**
     * @return array<string, array{array<array-key, mixed>, array<string, mixed>}>
     */
    public function malformedPayloads(): array
    {
        return [
            'non-string uri'          => [['resourceUri' => 123], ['resourceUri' => '']],
            'null uri'                => [['resourceUri' => null], ['resourceUri' => '']],
            'non-string adapter'      => [['adapter' => ['x']], ['adapter' => '']],
            'numeric-string priority' => [['priority' => '15'], ['priority' => 15]],
            'float priority'          => [['priority' => 15.9], ['priority' => 15]],
            'non-numeric priority'    => [['priority' => 'high'], ['priority' => 0]],
            'numeric-string at'       => [['at' => '2.5'], ['at' => 2.5]],
            'int at'                  => [['at' => 3], ['at' => 3.0]],
            'non-numeric at'          => [['at' => 'later'], ['at' => 0.0]],
            'null at'                 => [['at' => null], ['at' => 0.0]],
            'non-array trail'         => [['processorTrail' => 'CastingProcessor'], ['processorTrail' => []]],
            'trail drops non-strings' => [
                ['processorTrail' => ['A', 1, null, ['B'], 'C']],
                ['processorTrail' => ['A', 'C']],
            ],
            'truthy overridden'       => [['overridden' => 1], ['overridden' => false]],
            'strict true overridden'  => [['overridden' => true], ['overridden' => true]],
            'truthy sensitive'        => [['sensitive' => 'yes'], ['isSensitive' => false]],
            'strict true sensitive'   => [['sensitive' => true], ['isSensitive' => true]],
            'non-string shadowed'     => [['overriddenSource' => 5], ['overriddenSource' => null]],
            'non-string validatedBy'  => [['validatedBy' => false], ['validatedBy' => null]],
            'non-string layerId'      => [['layerId' => 7], ['layerId' => null]],
            'non-string format'       => [['format' => 42], ['format' => Format::UNKNOWN]],
        ];
    }

    public function testFromArrayAcceptsLowerCaseFormatNames(): void
    {
        verify(Provenance::fromArray(['format' => 'yaml'])->format())->equals(Format::YAML);
        verify(Provenance::fromArray(['format' => 'YAML'])->format())->equals(Format::YAML);
        verify(Provenance::fromArray(['format' => ' Json '])->format())->equals(Format::JSON);
    }

    /**
     * Documents the current asymmetry: an *absent* or non-string format degrades
     * to UNKNOWN, but an unrecognised format string is rejected outright.
     */
    public function testFromArrayRejectsAnUnrecognisedFormatString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Provenance::fromArray(['format' => 'protobuf']);
    }

    public function testSensitiveKeysSurviveTheCacheRoundTrip(): void
    {
        $secret = $this->provenance()->asSensitive();

        verify(Provenance::fromArray($secret->toArray())->isSensitive())->true();
    }
}
