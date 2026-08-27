<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use Nette\PhpGenerator\PhpNamespace;

class AdapterGeneratorTest extends Unit
{
    private static array $loaded = [];

    /**
     * Routes generated class names through a string return type so static analysis
     * treats them as runtime-only (the classes are emitted by the generator).
     */
    private function dynamicClass(string $fqcn): string
    {
        return $fqcn;
    }

    /**
     * Loads generated source into the runtime once per unique content so the
     * defined classes survive across test methods without redeclaration errors.
     */
    private function load(string $source): void
    {
        $hash = md5($source);
        if (isset(self::$loaded[$hash])) {
            return;
        }

        $dir = sys_get_temp_dir() . '/deriveit_adapter';
        @mkdir($dir);
        $file = $dir . '/' . $hash . '.php';
        file_put_contents($file, $source);

        require $file;
        self::$loaded[$hash] = true;
    }

    public function testAdapterMatchesGoldenFile(): void
    {
        $source = DeriveIt::deriveAdapter(\_Fixture\Tree::class);

        verify($source)->equals(file_get_contents(__DIR__ . '/../../_expected/Tree.adapter.php'));
    }

    public function testAdapterImplementsDerivedInterfaceAndDelegates(): void
    {
        $substitution = ['_Fixture\Collaborator' => '_Fixture\CollaboratorInterface'];
        $wrapper = ['_Fixture\CollaboratorInterface' => '_Fixture\CollaboratorAdapter'];

        $this->load(
            DeriveIt::deriveAdapter(
                \_Fixture\Service::class,
                $wrapper,
                new DeriveConfig(typeSubstitutionMap: $substitution),
            ),
        );
        $this->load(DeriveIt::deriveAdapter(\_Fixture\Collaborator::class));

        $serviceAdapter = $this->dynamicClass('\_Fixture\ServiceAdapter');
        $serviceInterface = $this->dynamicClass('\_Fixture\ServiceInterface');
        $collaboratorAdapter = $this->dynamicClass('\_Fixture\CollaboratorAdapter');

        $adapter = new $serviceAdapter(new \_Fixture\Service());

        verify($adapter)->instanceOf($serviceInterface);

        // pass-through scalar return
        verify($adapter->name())->equals('svc');

        // return value wrapped into its adapter
        $collaborator = $adapter->collaborator();
        verify($collaborator)->instanceOf($collaboratorAdapter);
        verify($collaborator->id())->equals(42);

        // nullable-return wrapping resolves to null
        verify($adapter->maybe())->null();

        // void method is callable without a return value
        $adapter->reset();
        verify(true)->true();
    }

    public function testGeneratedAdapterWrapsReturnValueInSource(): void
    {
        $substitution = ['_Fixture\Collaborator' => '_Fixture\CollaboratorInterface'];
        $wrapper = ['_Fixture\CollaboratorInterface' => '_Fixture\CollaboratorAdapter'];

        $source = DeriveIt::deriveAdapter(
            \_Fixture\Service::class,
            $wrapper,
            new DeriveConfig(typeSubstitutionMap: $substitution),
        );

        verify($source)->stringContainsString('implements ServiceInterface');
        verify($source)->stringContainsString(
            'return new \\_Fixture\\CollaboratorAdapter($this->adaptee->collaborator());',
        );
        verify($source)->stringContainsString(
            'return $value === null ? null : new \\_Fixture\\CollaboratorAdapter($value);',
        );
        verify($source)->stringContainsString('$this->adaptee->reset();');
        verify($source)->stringContainsString('private readonly Service $adaptee;');
    }

    public function testDogfoodNettePhpGeneratorRoundTrip(): void
    {
        $source = DeriveIt::deriveAdapter(PhpNamespace::class);
        $this->load($source);

        $namespace = new PhpNamespace('App');

        $adapterClass = $this->dynamicClass('\Nette\PhpGenerator\PhpNamespaceAdapter');
        $interface = $this->dynamicClass('\Nette\PhpGenerator\PhpNamespaceInterface');

        $adapter = new $adapterClass($namespace);

        verify($adapter)->instanceOf($interface);
        verify($adapter->getName())->equals('App');
    }
}
