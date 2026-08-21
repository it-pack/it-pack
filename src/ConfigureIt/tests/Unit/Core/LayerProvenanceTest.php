<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\CLI\ConfigurationInspector;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationLayer;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * @covers \ItPack\ConfigureIt\Core\ConfigurationLayer::id
 * @covers \ItPack\ConfigureIt\Core\LayeredSource
 * @covers \ItPack\ConfigureIt\Core\DocumentNormalizer
 * @covers \ItPack\ConfigureIt\CLI\ConfigurationInspector
 */
class LayerProvenanceTest extends Unit
{
    /**
     * @var list<string>
     */
    private array $created = [];

    protected function _before(): void
    {
        $this->created = [];
    }

    protected function _after(): void
    {
        foreach ($this->created as $dir) {
            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                unlink($dir . '/' . $item);
            }

            rmdir($dir);
        }
    }

    /**
     * @param array<string, string> $files map of filename => file contents
     */
    private function dirWith(array $files): string
    {
        $dir = sys_get_temp_dir() . '/cfg-layer-prov-' . uniqid();
        mkdir($dir, 0o777, true);

        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }

        $this->created[] = $dir;

        return $dir;
    }

    public function testLayerIdIsStampedOntoNodeProvenance(): void
    {
        $base = new FileSource($this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"], "app" => ["name" => "base"]];',
        ]));
        $override = new FileSource($this->dirWith([
            'override.php' => '<?php return ["db" => ["host" => "override-host"]];',
        ]));

        $snapshot = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addLayer(new ConfigurationLayer([$base], precedence: 10, id: 'base'))
            ->addLayer(new ConfigurationLayer([$override], precedence: 20, id: 'override'))
            ->build()
            ->resolve()
        ;

        // db.host is won by the higher-precedence layer.
        verify($snapshot->get('db.host'))->equals('override-host');
        verify($snapshot->provenance('db.host')?->layerId())->equals('override');

        // app.name only exists in the base layer.
        verify($snapshot->provenance('app.name')?->layerId())->equals('base');
    }

    public function testNoLayerIdWhenNoLayersRegistered(): void
    {
        $base = $this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"]];',
        ]);

        $snapshot = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addSource(new FileSource($base))
            ->build()
            ->resolve()
        ;

        verify($snapshot->get('db.host'))->equals('base-host');
        verify($snapshot->provenance('db.host')?->layerId())->null();
    }

    public function testWinningLayerIsVisibleInInspector(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'override-host',
                'override.php',
                20,
                [],
                new Provenance('override.php', Format::PHP, 'PhpLoader', 20, 'file', layerId: 'override'),
            ),
        ]);

        $explain = new ConfigurationInspector()->explain($graph, 'db.host');

        verify($explain)->stringContainsString('layer       : override');
        verify($explain)->stringContainsString('db.host');

        $table = new ConfigurationInspector()->explain($graph);
        verify($table)->stringContainsString('LAYER');
        verify($table)->stringContainsString('override');
    }
}
