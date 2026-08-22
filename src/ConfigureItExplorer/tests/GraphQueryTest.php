<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Tests;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureItExplorer\Query\GraphQuery;
use PHPUnit\Framework\TestCase;

/**
 * T9.2.3 — graph query returns the expected node sets.
 *
 * Nodes are hand-built with explicit provenance so each predicate has a
 * deterministic, observable target (no file I/O needed for the query unit).
 */
final class GraphQueryTest extends TestCase
{
    private function graph(): ConfigurationGraphInterface
    {
        return new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'db.local',
                'local.json',
                100,
                [],
                new Provenance('local.json', Format::JSON, 'JsonLoader', 100, 'file', overridden: true, overriddenSource: 'base.json'),
            ),
            new ConfigurationNode(
                'db.port',
                5432,
                'base.json',
                10,
                [],
                new Provenance('base.json', Format::JSON, 'JsonLoader', 10, 'file'),
            ),
            new ConfigurationNode(
                'secret.token',
                's3cr3t',
                'vault://token',
                200,
                [],
                new Provenance('vault://token', Format::ENV, 'VaultLoader', 200, 'vault', sensitive: true),
            ),
            new ConfigurationNode(
                'cache.ttl',
                60,
                'local.json',
                100,
                [],
                new Provenance('local.json', Format::JSON, 'JsonLoader', 100, 'file', processorTrail: ['EnvProcessor'], layerId: 'override'),
            ),
        ]);
    }

    public function testOverriddenBy(): void
    {
        self::assertSame(['db.host'], (new GraphQuery())->run($this->graph(), 'overridden-by:base.json'));
    }

    public function testSourceByResourceBasename(): void
    {
        self::assertSame(['db.port'], (new GraphQuery())->run($this->graph(), 'source:base.json'));
    }

    public function testSourceBySourceKind(): void
    {
        self::assertSame(['secret.token'], (new GraphQuery())->run($this->graph(), 'source:vault'));
    }

    public function testSensitive(): void
    {
        self::assertSame(['secret.token'], (new GraphQuery())->run($this->graph(), 'sensitive'));
    }

    public function testAdapter(): void
    {
        self::assertSame(['cache.ttl', 'db.host', 'db.port'], (new GraphQuery())->run($this->graph(), 'adapter:JsonLoader'));
    }

    public function testLayer(): void
    {
        self::assertSame(['cache.ttl'], (new GraphQuery())->run($this->graph(), 'layer:override'));
    }

    public function testTrail(): void
    {
        self::assertSame(['cache.ttl'], (new GraphQuery())->run($this->graph(), 'trail:EnvProcessor'));
    }

    public function testFormat(): void
    {
        self::assertSame(['cache.ttl', 'db.host', 'db.port'], (new GraphQuery())->run($this->graph(), 'format:json'));
    }

    public function testBareValueFallsBackToSource(): void
    {
        self::assertSame(['db.port'], (new GraphQuery())->run($this->graph(), 'base.json'));
    }

    public function testChangesBetweenGraphs(): void
    {
        $base = new ConfigurationGraph([
            new ConfigurationNode('a', 1, 'base.json', 1, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 1, 'file')),
            new ConfigurationNode('b', 2, 'base.json', 1, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 1, 'file')),
        ]);

        $other = new ConfigurationGraph([
            new ConfigurationNode('a', 1, 'base.json', 1, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 1, 'file')),
            new ConfigurationNode('c', 3, 'other.json', 1, [], new Provenance('other.json', Format::JSON, 'JsonLoader', 1, 'file')),
        ]);

        self::assertSame(['b', 'c'], (new GraphQuery())->changes($base, $other));
    }
}
