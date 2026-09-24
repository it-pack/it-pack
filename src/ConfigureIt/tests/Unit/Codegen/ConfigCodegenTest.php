<?php

declare(strict_types=1);

namespace Tests\Unit\Codegen;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Codegen\ConfigCodegen;
use ItPack\ConfigureIt\Codegen\ConfigDtoHydrator;
use ItPack\ConfigureIt\Codegen\GeneratedConfigDtoInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use Nette\Schema\Expect;
use Tests\Support\Extension\VirtualFileSystem;

class ConfigCodegenTest extends Unit
{
    use VirtualFileSystem;
    public function testGeneratesRootAndNestedDtoClasses(): void
    {
        $code = new ConfigCodegen()->generate($this->schema(), 'AppConfig', 'ItPack\\ConfigureIt\\Generated\\Inspect');

        verify($code)->stringContainsString('final readonly class AppConfig');
        verify($code)->stringContainsString('final readonly class AppConfigDb');
        verify($code)->stringContainsString('final readonly class AppConfigServersItem');
        verify($code)->stringContainsString('final readonly class AppConfigPeersItem');
        verify($code)->stringContainsString('implements GeneratedConfigDtoInterface');
        verify($code)->stringContainsString('public static function fromSnapshot(ConfigurationSnapshotInterface $s): self');
        verify($code)->stringContainsString('public function toArray(): array');
    }

    public function testHydratedDtoExposesTypedValues(): void
    {
        $dto = $this->hydrate($this->schema(), $this->snapshot(), 'AppConfig');

        verify($dto->toArray())->equals([
            'name' => 'svc',
            'port' => 8080,
            'debug' => true,
            'db' => [
                'host' => 'localhost',
                'pool' => [5432, 5433],
            ],
            'tags' => ['a', 'b'],
            'servers' => [
                ['ip' => '10.0.0.1'],
                ['ip' => '10.0.0.2'],
            ],
            'meta' => ['env' => 'prod'],
            'peers' => [
                'a' => ['host' => 'ha'],
                'b' => ['host' => 'hb'],
            ],
        ]);
    }

    public function testHydratorRejectsUnknownClass(): void
    {
        $this->expectException(\ItPack\ConfigureIt\Exception\ConfigurationException::class);

        new ConfigDtoHydrator()->hydrate('ItPack\\ConfigureIt\\Generated\\Nope', $this->snapshot());
    }
    private function schema(): \Nette\Schema\Schema
    {
        return Expect::structure([
            'name' => Expect::string(),
            'port' => Expect::int(),
            'debug' => Expect::bool(),
            'db' => Expect::structure([
                'host' => Expect::string(),
                'pool' => Expect::listOf(Expect::int()),
            ]),
            'tags' => Expect::listOf(Expect::string()),
            'servers' => Expect::listOf(Expect::structure([
                'ip' => Expect::string(),
            ])),
            'meta' => Expect::arrayOf(Expect::string()),
            'peers' => Expect::arrayOf(Expect::structure([
                'host' => Expect::string(),
            ])),
        ]);
    }

    private function snapshot(): ConfigurationSnapshot
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('name', 'svc', 'base', 10),
            new ConfigurationNode('port', 8080, 'base', 10),
            new ConfigurationNode('debug', true, 'base', 10),
            new ConfigurationNode('db.host', 'localhost', 'base', 10),
            new ConfigurationNode('db.pool', [5432, 5433], 'base', 10),
            new ConfigurationNode('tags', ['a', 'b'], 'base', 10),
            new ConfigurationNode('servers.0.ip', '10.0.0.1', 'base', 10),
            new ConfigurationNode('servers.1.ip', '10.0.0.2', 'base', 10),
            new ConfigurationNode('meta', ['env' => 'prod'], 'base', 10),
            new ConfigurationNode('peers.a.host', 'ha', 'base', 10),
            new ConfigurationNode('peers.b.host', 'hb', 'base', 10),
        ]);

        return ConfigurationSnapshot::fromGraph($graph);
    }

    private function hydrate(\Nette\Schema\Schema $schema, ConfigurationSnapshot $snapshot, string $class): GeneratedConfigDtoInterface
    {
        $namespace = 'ItPack\\ConfigureIt\\Generated\\H' . uniqid();
        $code = new ConfigCodegen()->generate($schema, $class, $namespace);
        $file = $this->vfsPath('/cfg-dto-' . uniqid() . '.php');
        file_put_contents($file, $code);
        require $file;

        return new ConfigDtoHydrator()->hydrate($namespace . '\\' . $class, $snapshot);
    }
}
