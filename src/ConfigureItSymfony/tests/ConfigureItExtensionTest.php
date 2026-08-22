<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony\Tests;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureItSymfony\ConfigureItExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ConfigureItExtensionTest extends TestCase
{
    /**
     * @param array{sources: list<string>, environment?: ?string, schema?: ?string, cache?: ?string} $config
     */
    private function boot(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        new ConfigureItExtension()->load([$config], $container);
        $container->compile();

        return $container;
    }

    public function testExposesResolvedSnapshot(): void
    {
        $container = $this->boot([
            'sources' => [__DIR__ . '/Support'],
            'environment' => 'production',
        ]);

        self::assertTrue($container->has(ConfigurationSnapshotInterface::class));

        $snapshot = $container->get(ConfigurationSnapshotInterface::class);

        self::assertInstanceOf(ConfigurationSnapshotInterface::class, $snapshot);
        self::assertSame('symfony-demo', $snapshot->string('service.name'));
        self::assertSame(9090, $snapshot->int('service.port'));
        self::assertTrue($snapshot->bool('service.feature.enabled'));
    }

    public function testExposesConfiguredBuilder(): void
    {
        $container = $this->boot(['sources' => [__DIR__ . '/Support']]);

        self::assertTrue($container->has(ConfigurationBuilder::class));
        self::assertInstanceOf(ConfigurationBuilder::class, $container->get(ConfigurationBuilder::class));
    }

    public function testYamlBundleConfigBootsAndResolves(): void
    {
        $yaml = sprintf(
            "configure_it:\n  sources:\n    - '%s'\n  environment: production\n",
            __DIR__ . '/Support',
        );

        $file = tempnam(sys_get_temp_dir(), 'cfg-it-') . '.yaml';
        file_put_contents($file, $yaml);

        try {
            $container = new ContainerBuilder();
            $container->registerExtension(new ConfigureItExtension());

            $loader = new YamlFileLoader(
                $container,
                new FileLocator(\dirname($file)),
            );
            $loader->load(basename($file));

            $container->compile();

            $snapshot = $container->get(ConfigurationSnapshotInterface::class);
            self::assertInstanceOf(ConfigurationSnapshotInterface::class, $snapshot);
            self::assertSame('symfony-demo', $snapshot->string('service.name'));
        } finally {
            @unlink($file);
        }
    }
}
