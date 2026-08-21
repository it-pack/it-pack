<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use Nette\Schema\Expect;

class NativeConfigurationSchemaTypedTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/configure-it-typed-' . uniqid();
        mkdir($this->dir, 0o777, true);

        file_put_contents($this->dir . '/config.php', "<?php return ['app' => ['name' => 'svc', 'port' => 8080, 'debug' => true]];");
        file_put_contents($this->dir . '/config.json', (string) json_encode(['app' => ['name' => 'svc', 'port' => 8080, 'debug' => true]]));
    }

    public function testEnumeratesScalarTypesFromNetteStructure(): void
    {
        $schema = new NativeConfigurationSchema(
            Expect::structure([
                'app' => Expect::structure([
                    'name' => Expect::string(),
                    'port' => Expect::int(),
                    'debug' => Expect::bool(),
                    'extra' => Expect::mixed(),
                ]),
            ])
        );

        verify($schema->scalarTypes())->equals([
            'app.name' => 'string',
            'app.port' => 'int',
            'app.debug' => 'bool',
        ]);
    }

    public function testResolverMintsTypedSnapshotWhenSchemaDeclaresTypes(): void
    {
        $schema = new NativeConfigurationSchema(
            Expect::structure([
                'app' => Expect::structure([
                    'name' => Expect::string(),
                    'port' => Expect::int(),
                    'debug' => Expect::bool(),
                ]),
            ])->otherItems(Expect::mixed())->castTo('array')
        );

        $resolver = new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
            ->withSchema($schema)
            ->build()
        ;

        $snapshot = $resolver->resolve();

        verify($snapshot->string('app.name'))->equals('svc');
        verify($snapshot->int('app.port'))->equals(8080);
        verify($snapshot->bool('app.debug'))->true();
    }

    protected function _after(): void
    {
        $files = glob($this->dir . '/*');

        if (\is_array($files)) {
            array_map('unlink', $files);
        }

        rmdir($this->dir);
    }
}
