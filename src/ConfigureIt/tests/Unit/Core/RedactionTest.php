<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\KubernetesSecretSource;
use ItPack\ConfigureIt\Adapter\Enterprise\VolumeReader;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\CLI\ConfigurationInspector;
use ItPack\ConfigureIt\CLI\GraphVisualizer;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\RedactingRenderer;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

use function str_contains;

use Tests\Support\Extension\VirtualFileSystem;

class RedactionTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    private string $configDir;

    public function testSecretSourceMarksValuesSensitive(): void
    {
        $snapshot = $this->secretSnapshot();

        verify($snapshot->get('db_password'))->equals('s3cr3t-value');
        verify($snapshot->isSensitive('db_password'))->true();
        verify($snapshot->provenance('db_password')?->isSensitive())->true();
        verify($snapshot->provenance('db_password')?->sourceKind())->equals('k8s-secret');

        // Non-secret values are not flagged.
        verify($snapshot->isSensitive('app_name'))->false();
    }

    public function testInspectorMasksSensitiveValues(): void
    {
        $snapshot = $this->secretSnapshot();
        $graph = $this->graphFromSnapshot($snapshot);

        $text = new ConfigurationInspector()->explain($graph);

        verify(str_contains($text, 's3cr3t-value'))->false();
        verify(str_contains($text, '****'))->true();
        // A non-secret key is still rendered in clear.
        verify(str_contains($text, 'demo'))->true();
    }

    public function testGraphVisualizerMasksSensitiveValues(): void
    {
        $snapshot = $this->secretSnapshot();
        $graph = $this->graphFromSnapshot($snapshot);

        $dot = new GraphVisualizer()->toDot($graph);
        $ascii = new GraphVisualizer()->toAscii($graph);

        verify(str_contains($dot, 's3cr3t-value'))->false();
        verify(str_contains($dot, '****'))->true();
        verify(str_contains($ascii, 's3cr3t-value'))->false();
        verify(str_contains($ascii, '****'))->true();
    }

    public function testDiffSummaryMasksSensitiveChanges(): void
    {
        $base = new ConfigurationGraph([
            new ConfigurationNode(
                'db.password',
                'old-value',
                'base.json',
                10,
                [],
                new Provenance('base.json', Format::JSON, 'JsonLoader', 10, SourceKind::File),
            ),
        ]);

        $other = new ConfigurationGraph([
            new ConfigurationNode(
                'db.password',
                's3cr3t-value',
                'k8s-secret://vault',
                20,
                [],
                new Provenance('k8s-secret://vault', Format::JSON, 'JsonLoader', 20, SourceKind::K8sSecret, sensitive: true),
            ),
        ]);

        $summary = new ConfigurationDiffer(new RedactingRenderer())->summarize(
            new ConfigurationDiffer(new RedactingRenderer())->diff($base, $other),
        );

        verify(str_contains($summary, 's3cr3t-value'))->false();
        verify(str_contains($summary, '****'))->true();
    }

    public function testNonSecretValuesAreNotMasked(): void
    {
        $renderer = new RedactingRenderer();

        verify($renderer->render('clear-text', false))->equals('clear-text');
        verify($renderer->render('clear-text', true))->equals('****');
        verify($renderer->render(['a' => 1], false))->equals('{"a":1}');
        verify($renderer->render(true, false))->equals('true');
    }

    public function testRedactionCannotBeBypassedViaRenderPaths(): void
    {
        $snapshot = $this->secretSnapshot();
        $graph = $this->graphFromSnapshot($snapshot);

        $inspector = new ConfigurationInspector()->explain($graph, 'db.password');
        $dot = new GraphVisualizer()->toDot($graph);

        foreach ([$inspector, $dot] as $output) {
            verify(str_contains($output, 's3cr3t-value'))->false();
        }
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/cfg-red-' . uniqid());
        mkdir($this->dir);
        file_put_contents($this->dir . '/db_password', 's3cr3t-value');

        $this->configDir = $this->vfsPath('/cfg-red-cfg-' . uniqid());
        mkdir($this->configDir);
        file_put_contents($this->configDir . '/config.json', '{"app_name":"demo"}');
    }

    protected function _after(): void
    {
        foreach ([$this->dir, $this->configDir] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                unlink($dir . '/' . $item);
            }

            rmdir($dir);
        }
    }

    private function secretSnapshot(): \ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface
    {
        return \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new VolumeReader())
            ->addReader(new FileReader())
            ->addSource(new KubernetesSecretSource($this->dir))
            ->addSource(new FileSource($this->configDir))
            ->addLoader(new EnvLoader())
            ->addLoader(new JsonLoader())
            ->build()
            ->resolve()
        ;
    }

    /**
     * @return ConfigurationGraph
     */
    private function graphFromSnapshot(\ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface $snapshot): ConfigurationGraph
    {
        $nodes = [];

        foreach ($snapshot->all() as $key => $value) {
            $provenance = $snapshot->provenance($key);
            $nodes[] = new ConfigurationNode(
                $key,
                $value,
                $provenance?->resourceUri() ?? 'unknown',
                $provenance?->priority() ?? 0,
                [],
                $provenance ?? new Provenance('unknown', Format::JSON, 'loader', 0, SourceKind::File),
            );
        }

        return new ConfigurationGraph($nodes);
    }
}
