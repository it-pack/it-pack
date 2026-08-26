<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\SemanticConventions;

/**
 * The OpenTelemetry naming contract: Occurrence types are mapped to span and
 * lifecycle-event names, and exactly one Occurrence type closes the resolution
 * trace. Unknown types must map to null so a new Occurrence never invents a span.
 *
 * @covers \ItPack\ConfigureIt\Bridge\OpenTelemetry\SemanticConventions
 */
final class SemanticConventionsTest extends Unit
{
    /**
     * @dataProvider occurrenceMappings
     */
    public function testMapsOccurrenceTypesToSpansAndEvents(
        string $type,
        ?string $expectedSpan,
        ?string $expectedEvent,
    ): void {
        verify(SemanticConventions::occurrenceSpan($type))->equals($expectedSpan);
        verify(SemanticConventions::occurrenceEvent($type))->equals($expectedEvent);
    }

    /**
     * @return array<string, array{string, ?string, ?string}>
     */
    public function occurrenceMappings(): array
    {
        return [
            'loaded'    => ['ConfigurationLoaded', 'config.load', 'config.loaded'],
            'merged'    => ['ConfigurationMerged', 'config.merge', 'config.merged'],
            'validated' => ['ConfigurationValidated', 'config.validate', 'config.validated'],
            'resolved'  => ['ConfigurationResolved', 'config.resolve.stages', 'config.resolved'],
            'snapshot'  => ['SnapshotCreated', 'config.snapshot', 'config.snapshot.created'],
            'drift'     => ['ConfigurationDrift', 'config.drift', 'config.drift'],
        ];
    }

    /**
     * @dataProvider unmappedOccurrenceTypes
     */
    public function testUnknownOccurrenceTypesMapToNull(string $type): void
    {
        verify(SemanticConventions::occurrenceSpan($type))->null();
        verify(SemanticConventions::occurrenceEvent($type))->null();
    }

    /**
     * @return array<string, array{string}>
     */
    public function unmappedOccurrenceTypes(): array
    {
        return [
            'empty'          => [''],
            'unknown type'   => ['ConfigurationExploded'],
            'wrong case'     => ['configurationloaded'],
            'span name'      => ['config.load'],
            'partial'        => ['Configuration'],
            'fqcn'           => ['ItPack\\ConfigureIt\\Execution\\Occurrence'],
        ];
    }

    /**
     * @dataProvider traceClosingTypes
     */
    public function testOnlySnapshotCreationClosesTheTrace(string $type, bool $expected): void
    {
        verify(SemanticConventions::closesTrace($type))->equals($expected);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function traceClosingTypes(): array
    {
        return [
            'snapshot created' => ['SnapshotCreated', true],
            'loaded'           => ['ConfigurationLoaded', false],
            'merged'           => ['ConfigurationMerged', false],
            'validated'        => ['ConfigurationValidated', false],
            'resolved'         => ['ConfigurationResolved', false],
            'drift'            => ['ConfigurationDrift', false],
            'empty'            => ['', false],
            'wrong case'       => ['snapshotcreated', false],
        ];
    }

    public function testSpanNamesAreDistinctAndNamespaced(): void
    {
        $spans = [
            SemanticConventions::SPAN_RESOLVE,
            SemanticConventions::SPAN_LOAD,
            SemanticConventions::SPAN_MERGE,
            SemanticConventions::SPAN_VALIDATE,
            SemanticConventions::SPAN_RESOLVE_STAGES,
            SemanticConventions::SPAN_SNAPSHOT,
            SemanticConventions::SPAN_DRIFT,
        ];

        verify(count(array_unique($spans)))->equals(count($spans));

        foreach ($spans as $span) {
            verify(str_starts_with($span, 'config.'))->true();
        }
    }

    public function testMetricAndAttributeKeysFollowTheConventions(): void
    {
        foreach ([
            SemanticConventions::METRIC_RESOLUTION_DURATION,
            SemanticConventions::METRIC_OCCURRENCES,
            SemanticConventions::METRIC_NODES,
            SemanticConventions::METRIC_OVERRIDES,
            SemanticConventions::METRIC_UNRESOLVED,
        ] as $metric) {
            verify(str_starts_with($metric, 'config.'))->true();
        }

        // Environment and service reuse the upstream OTel resource attributes
        // rather than a ConfigureIt-specific key.
        verify(SemanticConventions::ATTR_ENVIRONMENT)->equals('deployment.environment');
        verify(SemanticConventions::ATTR_SERVICE)->equals('service.name');
        verify(SemanticConventions::ATTR_SOURCE_KIND)->equals('config.source.kind');
    }
}
