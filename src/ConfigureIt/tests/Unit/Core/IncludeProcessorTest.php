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
use ItPack\ConfigureIt\ValueObject\ProcessorStage;

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

    public function testMissingBaseDirectoryIsRejectedBeforeAnyRead(): void
    {
        $processor = new IncludeProcessor(
            new FileReader(),
            new ConfigurationLoaderFactory([new YamlLoader()]),
            new DocumentNormalizer(),
            $this->dir . '/definitely-not-here',
        );

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('Include base directory');

        $processor->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.yaml', 'base', 10),
        ]));
    }

    public function testIncludeBelowAMissingSubdirectoryIsRejected(): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('escapes the base directory');

        $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include no/such/dir/extra.yaml', 'base', 10),
        ]));
    }

    public function testExtensionLessTargetHasNoDeterminableFormat(): void
    {
        file_put_contents($this->dir . '/plain', "value: inner\n");

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('Cannot determine format');

        $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include plain', 'base', 10),
        ]));
    }

    /**
     * A directive with no path at all degrades to the base directory itself,
     * which has no parsable format — reported instead of silently ignored.
     */
    public function testDirectiveWithoutAPathIsReportedAsAnUndeterminableFormat(): void
    {
        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('Cannot determine format');

        $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include ', 'base', 10),
        ]));
    }

    public function testUnknownExtensionIsReportedAsAnUndeterminableFormat(): void
    {
        file_put_contents($this->dir . '/extra.wat', "value: inner\n");

        $this->expectException(ResolutionException::class);
        $this->expectExceptionMessage('Cannot determine format');

        $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.wat', 'base', 10),
        ]));
    }

    /**
     * An include sitting under a nested key is grafted onto that key's parent
     * path, so `database.import` pulls `host` in as `database.host`.
     */
    public function testIncludeUnderANestedKeyIsPrefixedWithTheParentPath(): void
    {
        file_put_contents(
            $this->dir . '/db.yaml',
            "host: localhost\ncredentials:\n  user: root\n",
        );

        $next = $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('database.import', '@include db.yaml', 'base', 10),
        ]));

        verify($next->has('database.import'))->false();
        verify($next->node('database.host')->value())->equals('localhost');
        verify($next->node('database.credentials.user')->value())->equals('root');
        // Not grafted at the root.
        verify($next->has('host'))->false();
    }

    public function testDeeplyNestedDirectiveKeyKeepsTheWholeParentPrefix(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "value: inner\n");

        $next = $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('a.b.c.import', '@include extra.yaml', 'base', 10),
        ]));

        verify($next->node('a.b.c.value')->value())->equals('inner');
    }

    public function testNonDirectiveValuesArePreservedUntouched(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "value: inner\n");

        $next = $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('literal', '@includes-not-a-directive', 'base', 10),
            new ConfigurationNode('number', 42, 'base', 10),
            new ConfigurationNode('flag', true, 'base', 10),
            new ConfigurationNode('import', '@include extra.yaml', 'base', 10),
        ]));

        verify($next->node('literal')->value())->equals('@includes-not-a-directive');
        verify($next->node('number')->value())->equals(42);
        verify($next->node('flag')->value())->equals(true);
        verify($next->node('value')->value())->equals('inner');
    }

    public function testTheDirectivePrefixIsConfigurable(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "value: inner\n");

        $processor = new IncludeProcessor(
            new FileReader(),
            new ConfigurationLoaderFactory([new YamlLoader()]),
            new DocumentNormalizer(),
            $this->dir,
            directive: '@import',
        );

        $next = $processor->process(new ConfigurationGraph([
            new ConfigurationNode('a', '@import extra.yaml', 'base', 10),
            new ConfigurationNode('b', '@include extra.yaml', 'base', 10),
        ]));

        verify($next->node('value')->value())->equals('inner');
        // The default prefix is no longer special once overridden.
        verify($next->node('b')->value())->equals('@include extra.yaml');
    }

    public function testIncludedNodesOutrankTheDirectivePriority(): void
    {
        file_put_contents($this->dir . '/extra.yaml', "value: inner\n");

        $next = $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.yaml', 'base', 10),
        ]));

        verify($next->node('value')->priority())->equals(11);
        verify($next->node('value')->source())->equals('include:extra.yaml');
    }

    public function testProcessorReportsTheIncludeStage(): void
    {
        verify($this->processor()->stageName())->equals(ProcessorStage::Include->value);
    }

    public function testGraphWithoutDirectivesIsReturnedUnchanged(): void
    {
        $next = $this->processor()->process(new ConfigurationGraph([
            new ConfigurationNode('a', 1, 'base', 10),
            new ConfigurationNode('b', 2, 'base', 10),
        ]));

        verify($next->toArray())->equals(['a' => 1, 'b' => 2]);
    }
}
