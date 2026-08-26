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
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\Core\IncludeProcessor;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

/**
 * End-to-end verification of the M2 exit criteria: `addLayer()` ordering,
 * `@if` conditional includes, and layer attribution in provenance all compose,
 * while behaviour is unchanged when no layers are used.
 *
 * @covers \ItPack\ConfigureIt\Core\ConfigurationBuilder
 * @covers \ItPack\ConfigureIt\Core\ConfigurationLayer
 * @covers \ItPack\ConfigureIt\Core\LayeredSource
 * @covers \ItPack\ConfigureIt\Core\IncludeProcessor
 */
class M2ExitTest extends Unit
{
    private string $baseDir;

    private string $overrideDir;

    private string $inc;

    private string $plain;

    public function testLayerOrderingAndConditionalIncludeComposeUnderProd(): void
    {
        $snapshot = $this->build('prod')->build()->resolve();

        // Higher-precedence layer wins on db.host.
        verify($snapshot->get('db.host'))->equals('override-host');
        verify($snapshot->provenance('db.host')?->layerId())->equals('override');

        // Conditional include is applied (env matches) and attributed to base layer.
        verify($snapshot->has('feature'))->false();
        verify($snapshot->get('name'))->equals('prod-feature');
        verify($snapshot->provenance('name')?->layerId())->equals('base');
    }

    public function testConditionalIncludeSkippedUnderDevKeepsLayerAttribution(): void
    {
        $snapshot = $this->build('dev')->build()->resolve();

        verify($snapshot->get('db.host'))->equals('override-host');
        verify($snapshot->provenance('db.host')?->layerId())->equals('override');

        // Include skipped because env != prod; the directive node is dropped.
        verify($snapshot->has('feature'))->false();
        verify($snapshot->has('name'))->false();
    }

    public function testNoBehaviourChangeWhenNoLayersRegistered(): void
    {
        $snapshot = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addSource(new FileSource($this->plain))
            ->addProcessor(new IncludeProcessor(
                new FileReader(),
                new ConfigurationLoaderFactory([new PhpLoader()]),
                new DocumentNormalizer(),
                $this->inc,
            ))
            ->withExecutionContext(new ExecutionContext(environment: 'prod'))
            ->build()
            ->resolve()
        ;

        // Without layers, sources resolve exactly as before layering existed.
        verify($snapshot->get('db.host'))->equals('base-host');
        verify($snapshot->get('name'))->equals('prod-feature');
        verify($snapshot->provenance('db.host')?->layerId())->null();
    }

    public function testWinningLayerVisibleInInspector(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'override-host',
                'override.php',
                20,
                [],
                new Provenance('override.php', Format::PHP, 'PhpLoader', 20, SourceKind::File, layerId: 'override'),
            ),
        ]);

        $out = new ConfigurationInspector()->explain($graph, 'db.host');

        verify($out)->stringContainsString('layer       : override');
    }

    protected function _before(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/cfg-m2-base-' . uniqid();
        $this->overrideDir = sys_get_temp_dir() . '/cfg-m2-override-' . uniqid();
        $this->inc = sys_get_temp_dir() . '/cfg-m2-inc-' . uniqid();
        $this->plain = sys_get_temp_dir() . '/cfg-m2-plain-' . uniqid();
        mkdir($this->baseDir, 0o700, true);
        mkdir($this->overrideDir, 0o700, true);
        mkdir($this->inc, 0o700, true);
        mkdir($this->plain, 0o700, true);

        // base layer: provides db.host + a conditionally-included feature.
        file_put_contents(
            $this->baseDir . '/base.php',
            <<<'PHP'
                <?php return [
                    'db' => ['host' => 'base-host'],
                    'feature' => '@include extra.php @if env=prod',
                ];
                PHP
        );

        // override layer: wins on db.host (higher precedence).
        file_put_contents($this->overrideDir . '/override.php', '<?php return ["db" => ["host" => "override-host"]];');

        // included only when env=prod.
        file_put_contents($this->inc . '/extra.php', '<?php return ["name" => "prod-feature"];');

        // single-file source used to prove no behaviour change without layers.
        file_put_contents(
            $this->plain . '/base.php',
            <<<'PHP'
                <?php return [
                    'db' => ['host' => 'base-host'],
                    'feature' => '@include extra.php @if env=prod',
                ];
                PHP
        );
    }

    protected function _after(): void
    {
        foreach ([$this->baseDir, $this->overrideDir, $this->inc, $this->plain] as $dir) {
            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                unlink($dir . '/' . $item);
            }
            rmdir($dir);
        }
    }

    private function build(string $env): ConfigurationBuilder
    {
        return new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addProcessor(new IncludeProcessor(
                new FileReader(),
                new ConfigurationLoaderFactory([new PhpLoader()]),
                new DocumentNormalizer(),
                $this->inc,
            ))
            ->addLayer(new ConfigurationLayer(
                [new FileSource($this->baseDir)],
                precedence: 10,
                id: 'base',
            ))
            ->addLayer(new ConfigurationLayer(
                [new FileSource($this->overrideDir)],
                precedence: 20,
                id: 'override',
            ))
            ->withExecutionContext(new ExecutionContext(environment: $env))
        ;
    }
}
