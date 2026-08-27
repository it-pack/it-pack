<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\Core\IncludeProcessor;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;

/**
 * @covers \ItPack\ConfigureIt\Core\IncludeProcessor
 * @covers \ItPack\ConfigureIt\Core\IncludeProcessor::conditionMatches
 * @covers \ItPack\ConfigureIt\Core\IncludeProcessor::parseDirective
 * @covers \ItPack\ConfigureIt\Core\IncludeProcessor::withExecutionContext
 */
class ConditionalIncludeTest extends Unit
{
    private string $dir;

    public function testIncludedWhenConditionMatches(): void
    {
        file_put_contents($this->dir . '/extra.php', '<?php return ["value" => "yes"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php @if env=prod', 'base', 10),
        ]);

        $next = $this->processor(new ExecutionContext(environment: 'prod'))->process($graph);

        verify($next->has('import'))->false();
        verify($next->node('value')->value())->equals('yes');
    }

    public function testExcludedWhenConditionFails(): void
    {
        file_put_contents($this->dir . '/extra.php', '<?php return ["value" => "yes"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php @if env=prod', 'base', 10),
        ]);

        $next = $this->processor(new ExecutionContext(environment: 'dev'))->process($graph);

        verify($next->has('import'))->false();
        verify($next->has('value'))->false();
    }

    public function testIncludedWhenNoConditionBackwardCompat(): void
    {
        file_put_contents($this->dir . '/extra.php', '<?php return ["value" => "yes"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php', 'base', 10),
        ]);

        $next = $this->processor(new ExecutionContext(environment: 'dev'))->process($graph);

        verify($next->node('value')->value())->equals('yes');
    }

    public function testTenantConditionGatesInclude(): void
    {
        file_put_contents($this->dir . '/extra.php', '<?php return ["value" => "yes"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php @if tenant=acme', 'base', 10),
        ]);

        $matched = $this->processor(new ExecutionContext(attributes: ['tenant' => 'acme']))->process($graph);
        verify($matched->has('value'))->true();

        $skipped = $this->processor(new ExecutionContext(attributes: ['tenant' => 'other']))->process($graph);
        verify($skipped->has('value'))->false();
    }

    public function testProfileConditionGatesInclude(): void
    {
        file_put_contents($this->dir . '/extra.php', '<?php return ["value" => "yes"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php @if profile=canary', 'base', 10),
        ]);

        $matched = $this->processor(new ExecutionContext(attributes: ['profile' => 'canary']))->process($graph);
        verify($matched->has('value'))->true();

        $skipped = $this->processor(new ExecutionContext(attributes: []))->process($graph);
        verify($skipped->has('value'))->false();
    }

    public function testInvalidConditionThrows(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include extra.php @if bogus=1', 'base', 10),
        ]);

        $this->expectException(ResolutionException::class);

        $this->processor()->process($graph);
    }

    public function testMultiPassNestedIncludesViaDoubleAdd(): void
    {
        file_put_contents($this->dir . '/lvl1.php', '<?php return ["deep" => "@include lvl2.php"];');
        file_put_contents($this->dir . '/lvl2.php', '<?php return ["value" => "resolved"];');

        $graph = new ConfigurationGraph([
            new ConfigurationNode('import', '@include lvl1.php', 'base', 10),
        ]);

        $processor = $this->processor();

        // Single pass resolves only the first level; the nested include remains a directive.
        $once = $processor->process($graph);
        verify($once->has('deep'))->true();
        verify($once->node('deep')->value())->equals('@include lvl2.php');

        // Adding the processor twice (double-add) resolves nested includes.
        $twice = $processor->process($once);
        verify($twice->has('value'))->true();
        verify($twice->node('value')->value())->equals('resolved');
        verify($twice->has('deep'))->false();
    }

    public function testBuilderInjectsExecutionContextIntoIncludeProcessor(): void
    {
        $conf = $this->dir . '/conf';
        $inc = $this->dir . '/inc';
        mkdir($conf, 0o700, true);
        mkdir($inc, 0o700, true);

        file_put_contents($conf . '/base.php', '<?php return ["import" => "@include extra.php @if env=prod"];');
        file_put_contents($inc . '/extra.php', '<?php return ["value" => "yes"];');

        $build = fn (string $env): bool => \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addSource(new \ItPack\ConfigureIt\Adapter\FileSource($conf))
            ->addProcessor(new IncludeProcessor(
                new FileReader(),
                new ConfigurationLoaderFactory([new PhpLoader()]),
                new DocumentNormalizer(),
                $inc,
                new ConfigurationResourceFactory(),
                new ExecutionContext()
            ))
            ->withExecutionContext(new ExecutionContext(environment: $env))
            ->build()
            ->resolve()
            ->has('value')
        ;

        verify($build('prod'))->true();
        verify($build('dev'))->false();
    }

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-cond-include-' . uniqid();
        mkdir($this->dir, 0o700, true);
    }

    protected function _after(): void
    {
        $this->removeDir($this->dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }

    private function processor(ExecutionContext $context = new ExecutionContext()): IncludeProcessor
    {
        return new IncludeProcessor(
            new FileReader(),
            new ConfigurationLoaderFactory([new PhpLoader()]),
            new DocumentNormalizer(),
            $this->dir,
            new ConfigurationResourceFactory(),
            context: $context,
        );
    }
}
