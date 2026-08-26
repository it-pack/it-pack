<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationPipelineInterface;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationResolver;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\Format;
use Nette\Schema\Expect;

class ConfigurationResolverTest extends Unit
{
    private string $dir;

    public function testResolvesAndMergesSourcesIntoImmutableSnapshot(): void
    {
        $reporter = new ResolutionReporter();

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->build($reporter)
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->get('db.host'))->equals('local-host');
        verify($snapshot->get('app.name'))->equals('base');
        verify($snapshot->get('app.debug'))->true();

        $occurrences = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationLoaded', $occurrences, true))->true();
        verify(in_array('ConfigurationResolved', $occurrences, true))->true();
        verify(in_array('SnapshotCreated', $occurrences, true))->true();
    }

    public function testAcceptsPipelineInterfaceImplementation(): void
    {
        $pipeline = new class () implements ConfigurationPipelineInterface {
            public function run(iterable $documents): ConfigurationGraphInterface
            {
                return new ConfigurationGraph([]);
            }
        };

        $reader = new class () implements ConfigurationReaderInterface {
            public function read(ConfigurationResourceInterface $resource): string
            {
                return '{}';
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return true;
            }
        };

        $loaders = new class () implements ConfigurationLoaderFactoryInterface {
            public function build(Format $format): ConfigurationLoaderInterface
            {
                return new JsonLoader();
            }
        };

        $resolver = new ConfigurationResolver($reader, $loaders, $pipeline);

        verify($resolver->resolve([]))->instanceOf(ConfigurationSnapshotInterface::class);
    }

    public function testThrowsWhenNoLoaderSupportsFormat(): void
    {
        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->build()
        ;

        $this->expectException(\ItPack\ConfigureIt\Exception\ResolutionException::class);

        $resolver->resolve();
    }

    public function testValidatesAgainstSchemaThroughBuilder(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'db' => Expect::structure(['host' => Expect::string()]),
        ])->otherItems(Expect::mixed())->castTo('array'));

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($schema)
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->has('db.host'))->true();
        verify($snapshot->get('db.host'))->equals('local-host');
    }

    public function testThrowsValidationExceptionOnSchemaFailure(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'db' => Expect::structure(['host' => Expect::int()]),
        ])->otherItems(Expect::mixed())->castTo('array'));

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($schema)
            ->build()
        ;

        $this->expectException(\ItPack\ConfigureIt\Exception\ValidationException::class);

        $resolver->resolve();
    }

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-resolve-' . uniqid();
        mkdir($this->dir, 0o700, true);
        file_put_contents($this->dir . '/base.php', '<?php return ["db" => ["host" => "base-host"], "app" => ["name" => "base"]];');
        file_put_contents($this->dir . '/local.json', '{"db":{"host":"local-host"},"app":{"debug":true}}');
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }
}
