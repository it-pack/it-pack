<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\YamlLoader;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\Core\IncludeProcessor;
use ItPack\ConfigureIt\Exception\ResolutionException;

class IncludeProcessorTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-include-' . uniqid();
        mkdir($this->dir, 0o777, true);
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }
        rmdir($this->dir);
    }

    public function testDotDotTraversalOutOfTheBaseDirectoryIsRejected(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include ../outside.yaml', 'base', 10),
        ]);

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('escapes the base directory');

        $this->processor()->process($graph);
    }

    public function testDeepTraversalStillLandsInsideBase(): void
    {
        mkdir($this->dir . '/sub');
        file_put_contents($this->dir . '/extra.yaml', "value: inner\n");

        try {
            $graph = new ConfigurationGraph([
                new ConfigurationNode('import', '@include sub/../extra.yaml', 'base', 10),
            ]);

            $next = $this->processor()->process($graph);

            verify($next->node('value')->value())->equals('inner');
        } finally {
            rmdir($this->dir . '/sub');
        }
    }

    public function testSymlinkEscapeIsRejected(): void
    {
        $outsideDir = sys_get_temp_dir() . '/cfg-include-out-' . uniqid();
        mkdir($outsideDir, 0o700, true);
        file_put_contents($outsideDir . '/secret.yaml', "value: stolen\n");

        try {
            symlink($outsideDir . '/secret.yaml', $this->dir . '/link.yaml');

            $graph = new ConfigurationGraph([
                new ConfigurationNode('import', '@include link.yaml', 'base', 10),
            ]);

            $this->expectException(ResolutionException::class);
            $this->expectExceptionMessage('escapes the base directory');

            $this->processor()->process($graph);
        } finally {
            @unlink($this->dir . '/link.yaml');
            @unlink($outsideDir . '/secret.yaml');
            @rmdir($outsideDir);
        }
    }

    private function processor(): IncludeProcessor
    {
        return new IncludeProcessor(
            new FileReader(),
            new ConfigurationLoaderFactory([new YamlLoader()]),
            new DocumentNormalizer(),
            $this->dir,
        );
    }

    public function testInlinesReferencedFileAndDropsDirective(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "feature:\n  enabled: true\nname: imported\n");

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.yaml', 'base', 10),
        ]);

        $next = $this->processor()->process($graph);

        verify($next->has('import'))->false();
        verify($next->node('feature.enabled')->value())->equals(true);
        verify($next->node('name')->value())->equals('imported');
    }

    public function testIncludedNodesOverrideIncludingDocument(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "value: from-include\n");

        $graph = new ConfigurationGraph([
            new ConfigurationNode('value', 'from-base', 'base', 10),
            new ConfigurationNode('import', '@include extra.yaml', 'base', 10),
        ]);

        $next = $this->processor()->process($graph);

        verify($next->node('value')->value())->equals('from-include');
    }

    public function testThrowsWhenTargetMissing(): void
    {
        $this->expectException(ResolutionException::class);

        new IncludeProcessor(
            new FileReader(),
            new ConfigurationLoaderFactory([new YamlLoader()]),
            new DocumentNormalizer(),
            $this->dir,
        )->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include missing.yaml', 'base', 10),
        ]));
    }
}
