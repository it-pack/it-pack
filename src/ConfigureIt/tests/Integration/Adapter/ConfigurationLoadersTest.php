<?php

declare(strict_types=1);

namespace Tests\Integration\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\ValueObject\Format;

class ConfigurationLoadersTest extends Unit
{
    public function testDefaultSetCoversEveryBundledFormat(): void
    {
        $loaders = ConfigurationLoaders::default();

        $supported = [];
        foreach ($loaders as $loader) {
            foreach (Format::cases() as $format) {
                if ($loader->supports($format)) {
                    $supported[$format->name] = true;
                }
            }
        }

        foreach ([Format::PHP, Format::JSON, Format::YAML, Format::TOML, Format::INI, Format::XML, Format::FRONTMATTER, Format::DOTENV] as $format) {
            verify(isset($supported[$format->name]))->true();
        }
    }

    public function testResolvesMultipleFormatsEndToEnd(): void
    {
        $dir = sys_get_temp_dir() . '/cfg-loaders-' . uniqid();
        mkdir($dir, 0o700, true);

        try {
            file_put_contents($dir . '/base.yaml', "db:\n  host: yaml-host\napp:\n  name: yaml\n");
            file_put_contents($dir . '/extra.toml', "[feature]\nenabled = true\n");
            file_put_contents($dir . '/meta.md', "---\ntitle: Docs\n---\nBody\n");
            file_put_contents($dir . '/vars.env', "dotenv_key=from-env-file\n");

            $resolver = new ConfigurationBuilder()
                ->addReader(new FileReader())
                ->addSource(new FileSource($dir))
                ->addLoaders(ConfigurationLoaders::default())
                ->build()
            ;

            $snapshot = $resolver->resolve();

            verify($snapshot->get('db.host'))->equals('yaml-host');
            verify($snapshot->get('feature.enabled'))->equals(true);
            verify($snapshot->get('title'))->equals('Docs');
            verify($snapshot->get('dotenv_key'))->equals('from-env-file');
        } finally {
            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                unlink($dir . '/' . $item);
            }
            rmdir($dir);
        }
    }
}
