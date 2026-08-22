<?php

declare(strict_types=1);

namespace ItPack\ConfigureItPsr11\Tests;

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureItPsr11\ConfigureItContainerFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

final class ConfigureItContainerTest extends TestCase
{
    private function resolve(): ConfigurationSnapshotInterface
    {
        return new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource(__DIR__ . '/Support'))
            ->addLoaders(ConfigurationLoaders::default())
            ->build()
            ->resolve();
    }

    public function testServesResolvedSnapshotUnderInterfaceId(): void
    {
        $snapshot = $this->resolve();
        $container = ConfigureItContainerFactory::fromSnapshot($snapshot);

        self::assertTrue($container->has(ConfigurationSnapshotInterface::class));

        $served = $container->get(ConfigurationSnapshotInterface::class);

        self::assertSame($snapshot, $served);
        self::assertSame('demo', $served->string('service.name'));
        self::assertSame(8080, $served->int('service.port'));
        self::assertTrue($served->bool('service.feature.enabled'));
    }

    public function testUnknownEntryIsNotFound(): void
    {
        $container = ConfigureItContainerFactory::fromSnapshot($this->resolve());

        self::assertFalse($container->has('app.unknown'));

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('app.unknown');
    }

    public function testCustomEntryId(): void
    {
        $snapshot = $this->resolve();
        $container = ConfigureItContainerFactory::fromSnapshot($snapshot, 'config');

        self::assertTrue($container->has('config'));
        self::assertFalse($container->has(ConfigurationSnapshotInterface::class));
        self::assertSame($snapshot, $container->get('config'));
    }

    public function testFactoryBuildsFromBuilder(): void
    {
        $builder = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource(__DIR__ . '/Support'))
            ->addLoaders(ConfigurationLoaders::default());

        $container = ConfigureItContainerFactory::fromBuilder($builder);

        $served = $container->get(ConfigurationSnapshotInterface::class);

        self::assertInstanceOf(ConfigurationSnapshotInterface::class, $served);
        self::assertSame('demo', $served->string('service.name'));
    }
}
