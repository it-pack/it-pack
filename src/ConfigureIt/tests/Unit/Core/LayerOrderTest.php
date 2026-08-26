<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationLayer;
use ItPack\ConfigureIt\Execution\ExecutionContext;

/**
 * @covers \ItPack\ConfigureIt\Core\ConfigurationLayer
 * @covers \ItPack\ConfigureIt\Core\ConfigurationBuilder::addLayer
 * @covers \ItPack\ConfigureIt\Core\ConfigurationBuilder::build
 */
class LayerOrderTest extends Unit
{
    /**
     * @var list<string>
     */
    private array $created = [];

    public function testLayersAreOrderedByPrecedenceRegardlessOfInsertionOrder(): void
    {
        $base = new FileSource($this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"], "app" => ["name" => "base"]];',
        ]));
        $override = new FileSource($this->dirWith([
            'override.php' => '<?php return ["db" => ["host" => "override-host"], "app" => ["debug" => true]];',
        ]));

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addLayer(new ConfigurationLayer([$override], precedence: 20))
            ->addLayer(new ConfigurationLayer([$base], precedence: 10))
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('override-host');
        verify($snapshot->get('app.name'))->equals('base');
        verify($snapshot->get('app.debug'))->true();
    }

    public function testEnvGatedLayerIsSkippedWhenMatchReturnsFalse(): void
    {
        $base = new FileSource($this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"]];',
        ]));
        $override = new FileSource($this->dirWith([
            'override.php' => '<?php return ["db" => ["host" => "override-host"]];',
        ]));
        $prod = new FileSource($this->dirWith([
            'prod.php' => '<?php return ["db" => ["host" => "prod-host"]];',
        ]));

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addLayer(new ConfigurationLayer([$base], precedence: 10))
            ->addLayer(new ConfigurationLayer([$override], precedence: 20))
            ->addLayer(new ConfigurationLayer(
                [$prod],
                precedence: 30,
                match: static fn (ExecutionContextInterface $ctx): bool => 'prod' === $ctx->environment(),
            ))
            ->withExecutionContext(new ExecutionContext(environment: 'dev'))
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('override-host');
    }

    public function testEnvGatedLayerIsAppliedWhenMatchReturnsTrue(): void
    {
        $base = new FileSource($this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"]];',
        ]));
        $override = new FileSource($this->dirWith([
            'override.php' => '<?php return ["db" => ["host" => "override-host"]];',
        ]));
        $prod = new FileSource($this->dirWith([
            'prod.php' => '<?php return ["db" => ["host" => "prod-host"]];',
        ]));

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addLayer(new ConfigurationLayer([$base], precedence: 10))
            ->addLayer(new ConfigurationLayer([$override], precedence: 20))
            ->addLayer(new ConfigurationLayer(
                [$prod],
                precedence: 30,
                match: static fn (ExecutionContextInterface $ctx): bool => 'prod' === $ctx->environment(),
            ))
            ->withExecutionContext(new ExecutionContext(environment: 'prod'))
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('prod-host');
    }

    public function testBackwardCompatibleWhenNoLayersRegistered(): void
    {
        $base = $this->dirWith([
            'base.php' => '<?php return ["db" => ["host" => "base-host"]];',
        ]);

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addSource(new FileSource($base))
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('base-host');
    }

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
        $dir = sys_get_temp_dir() . '/cfg-layer-' . uniqid();
        mkdir($dir, 0o700, true);

        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }

        $this->created[] = $dir;

        return $dir;
    }
}
