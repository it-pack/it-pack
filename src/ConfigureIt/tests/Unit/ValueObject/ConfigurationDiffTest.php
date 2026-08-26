<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\ConfigurationDiff;

/**
 * Pure value-object coverage for the semantic graph diff: emptiness, the
 * serializable form, and the three summary sections (added / removed / changed)
 * including the provenance-attribution fallbacks.
 *
 * @covers \ItPack\ConfigureIt\ValueObject\ConfigurationDiff
 */
final class ConfigurationDiffTest extends Unit
{
    /**
     * @param array<string, mixed> $fromProvenance
     * @param array<string, mixed> $toProvenance
     * @return array{key:string, from:mixed, to:mixed, fromProvenance:array<string, mixed>, toProvenance:array<string, mixed>, sensitive:bool}
     */
    private static function entry(
        string $key = 'b',
        mixed $from = 2,
        mixed $to = 20,
        array $fromProvenance = ['resourceUri' => 'base.json'],
        array $toProvenance = ['resourceUri' => 'local.json'],
        bool $sensitive = false,
    ): array {
        return [
            'key'            => $key,
            'from'           => $from,
            'to'             => $to,
            'fromProvenance' => $fromProvenance,
            'toProvenance'   => $toProvenance,
            'sensitive'      => $sensitive,
        ];
    }

    public function testSensitiveMapsDefaultToEmpty(): void
    {
        $diff = new ConfigurationDiff([], [], []);

        verify($diff->addedSensitive)->equals([]);
        verify($diff->removedSensitive)->equals([]);
        verify($diff->empty())->true();
    }

    public function testExposesEveryChangeSetVerbatim(): void
    {
        $diff = new ConfigurationDiff(
            ['c' => 3],
            ['d' => 4],
            [self::entry()],
            ['c' => true],
            ['d' => false],
        );

        verify($diff->added)->equals(['c' => 3]);
        verify($diff->removed)->equals(['d' => 4]);
        verify($diff->changed)->equals([self::entry()]);
        verify($diff->addedSensitive)->equals(['c' => true]);
        verify($diff->removedSensitive)->equals(['d' => false]);
    }

    /**
     * @param array<string, mixed> $added
     * @param array<string, mixed> $removed
     * @param list<array{key:string, from:mixed, to:mixed, fromProvenance:array<string, mixed>, toProvenance:array<string, mixed>, sensitive:bool}> $changed
     *
     * @dataProvider emptinessMatrix
     */
    public function testEmptinessConsidersAllThreeChangeSets(
        array $added,
        array $removed,
        array $changed,
        bool $expected,
    ): void {
        verify(new ConfigurationDiff($added, $removed, $changed)->empty())->equals($expected);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, list<array{key:string, from:mixed, to:mixed, fromProvenance:array<string, mixed>, toProvenance:array<string, mixed>, sensitive:bool}>, bool}>
     */
    public function emptinessMatrix(): array
    {
        return [
            'nothing at all'      => [[], [], [], true],
            'added only'          => [['a' => 1], [], [], false],
            'removed only'        => [[], ['a' => 1], [], false],
            'changed only'        => [[], [], [self::entry()], false],
            'added and removed'   => [['a' => 1], ['b' => 2], [], false],
            'all three'           => [['a' => 1], ['b' => 2], [self::entry()], false],
            // A null value is still a present key: emptiness is key-set based.
            'added null value'    => [['a' => null], [], [], false],
        ];
    }

    public function testToArrayExposesOnlyTheThreeChangeSets(): void
    {
        $diff = new ConfigurationDiff(
            ['c' => 3],
            ['d' => 4],
            [self::entry()],
            ['c' => true],
            ['d' => true],
        );

        $array = $diff->toArray();

        // The sensitivity maps are deliberately not part of the serialized form,
        // so a toArray() round trip loses the secret flags.
        verify(array_keys($array))->equals(['added', 'removed', 'changed']);
        verify($array['added'])->equals(['c' => 3]);
        verify($array['removed'])->equals(['d' => 4]);
        verify($array['changed'])->equals([self::entry()]);
    }

    public function testIdenticalGraphsSummarizeAsIdentical(): void
    {
        verify(new ConfigurationDiff([], [], [])->summary())
            ->equals('Configuration graphs are identical.')
        ;
    }

    public function testSummaryRendersAddedSection(): void
    {
        $summary = new ConfigurationDiff(['app.name' => 'demo'], [], [])->summary();

        verify($summary)->equals(
            "Configuration diff:\n  added:\n    + app.name = \"demo\"",
        );
    }

    public function testSummaryRendersRemovedSection(): void
    {
        $summary = new ConfigurationDiff([], ['legacy.flag' => 1], [])->summary();

        verify($summary)->equals(
            "Configuration diff:\n  removed:\n    - legacy.flag = 1",
        );
    }

    public function testSummaryRendersChangedSectionWithBasenamedSources(): void
    {
        $summary = new ConfigurationDiff([], [], [
            self::entry(
                key: 'db.port',
                from: 5432,
                to: 6432,
                fromProvenance: ['resourceUri' => '/etc/app/base.json'],
                toProvenance: ['resourceUri' => '/srv/deploy/local.json'],
            ),
        ])->summary();

        verify($summary)->equals(
            "Configuration diff:\n  changed:\n    ~ db.port: 5432 -> 6432 (local.json overrode base.json)",
        );
    }

    public function testSummaryRendersAllSectionsInDeclaredOrder(): void
    {
        $summary = new ConfigurationDiff(
            ['added.key' => true],
            ['removed.key' => false],
            [self::entry(key: 'changed.key', from: 'old', to: 'new')],
        )->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diff:',
            '  added:',
            '    + added.key = true',
            '  removed:',
            '    - removed.key = false',
            '  changed:',
            '    ~ changed.key: "old" -> "new" (local.json overrode base.json)',
        ]);
    }

    /**
     * @param array<string, mixed> $provenance
     *
     * @dataProvider provenanceAttributionMatrix
     */
    public function testSummaryFallsBackWhenResourceUriIsUnusable(array $provenance, string $expected): void
    {
        $summary = new ConfigurationDiff([], [], [
            self::entry(key: 'k', from: 1, to: 2, fromProvenance: $provenance, toProvenance: $provenance),
        ])->summary();

        verify($summary)->equals(
            sprintf("Configuration diff:\n  changed:\n    ~ k: 1 -> 2 (%s overrode %s)", $expected, $expected),
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public function provenanceAttributionMatrix(): array
    {
        return [
            'plain file name'   => [['resourceUri' => 'local.json'], 'local.json'],
            'absolute path'     => [['resourceUri' => '/a/b/local.json'], 'local.json'],
            'trailing slash'    => [['resourceUri' => '/a/b/'], 'b'],
            'numeric uri'       => [['resourceUri' => 5], '5'],
            'boolean uri'       => [['resourceUri' => true], '1'],
            'missing key'       => [[], '?'],
            'null uri'          => [['resourceUri' => null], '?'],
            'array uri'         => [['resourceUri' => ['nested']], '?'],
            'unrelated keys'    => [['adapter' => 'JsonLoader'], '?'],
        ];
    }

    /**
     * @dataProvider valueRenderingMatrix
     */
    public function testSummaryRendersValuesAsJson(mixed $value, string $expected): void
    {
        $summary = new ConfigurationDiff(['k' => $value], [], [])->summary();

        verify($summary)->equals("Configuration diff:\n  added:\n    + k = " . $expected);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public function valueRenderingMatrix(): array
    {
        return [
            'string'       => ['demo', '"demo"'],
            'empty string' => ['', '""'],
            'int'          => [42, '42'],
            'negative int' => [-7, '-7'],
            'float'        => [0.5, '0.5'],
            'true'         => [true, 'true'],
            'false'        => [false, 'false'],
            'null'         => [null, 'null'],
            'list'         => [[1, 2], '[1,2]'],
            'map'          => [['a' => 1], '{"a":1}'],
            'empty array'  => [[], '[]'],
        ];
    }

    public function testSummaryIsTheUnredactedVariant(): void
    {
        // ConfigurationDiff::summary() renders raw values; masking of secrets is
        // the responsibility of ConfigurationDiffer::summarize(), which routes
        // every value through the central RedactingRenderer.
        $diff = new ConfigurationDiff(
            ['db.password' => 'plain-text'],
            [],
            [self::entry(key: 'db.token', from: 'old-token', to: 'new-token', sensitive: true)],
            ['db.password' => true],
        );

        verify(str_contains($diff->summary(), 'plain-text'))->true();
        verify(str_contains($diff->summary(), 'new-token'))->true();
        verify(str_contains($diff->summary(), '****'))->false();

        // The flags themselves survive so the redacting summarizer can use them.
        verify($diff->addedSensitive['db.password'])->true();
        verify($diff->changed[0]['sensitive'])->true();
    }
}
