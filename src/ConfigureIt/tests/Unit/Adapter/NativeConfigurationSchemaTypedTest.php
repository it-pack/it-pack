<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;

use function is_array;

use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Adapter\Processor;
use ItPack\ConfigureIt\ValueObject\ValidationResultFactory;
use Nette\Schema\Expect;

class NativeConfigurationSchemaTypedTest extends Unit
{
    private string $dir;

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
            ]),
            new Processor(),
            new ValidationResultFactory()
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
            ])->otherItems(Expect::mixed())->castTo('array'),
            new Processor(),
            new ValidationResultFactory()
        );

        $resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
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

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/configure-it-typed-' . uniqid();
        mkdir($this->dir, 0o700, true);

        file_put_contents($this->dir . '/config.php', "<?php return ['app' => ['name' => 'svc', 'port' => 8080, 'debug' => true]];");
        file_put_contents($this->dir . '/config.json', (string) json_encode(['app' => ['name' => 'svc', 'port' => 8080, 'debug' => true]]));
    }

    protected function _after(): void
    {
        $files = glob($this->dir . '/*');

        if (is_array($files)) {
            array_map('unlink', $files);
        }

        rmdir($this->dir);
    }
}
