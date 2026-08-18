<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\DeriverInterface;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;

/**
 * Assembles a self-contained interface from a class's effective public surface.
 *
 * Leakage invariant: a flat-derived interface must extend / reference nothing from
 * the adaptee's own parent classes, traits, or interfaces. In flat mode the
 * assembled InterfaceType therefore has an empty extends list and no trait uses.
 */
final class InterfaceDeriver implements DeriverInterface
{
    private readonly MethodExtractor $extractor;
    private readonly TypeSubstitutor $substitutor;

    public function __construct(
        private readonly MethodSignatureFactory $signatureFactory = new MethodSignatureFactory(),
        DeriveConfig $config = new DeriveConfig(),
    ) {
        $this->extractor = new MethodExtractor($config);
        $this->substitutor = new TypeSubstitutor($config->typeSubstitutionMap);
    }

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;

        $interface = new InterfaceType($this->deriveName($reflection));

        $methods = [];
        foreach ($this->extractor->extract($reflection) as $method) {
            $methods[] = $this->signatureFactory->fromMethod($method, $this->substitutor);
        }
        $interface->setMethods($methods);

        self::assertSelfContained($interface);

        return $interface;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;

        $interface = $this->derive($reflection);

        $namespace = new PhpNamespace($reflection->getNamespaceName());
        $namespace->add($interface);

        $file = new PhpFile();
        $file->addNamespace($namespace);

        return (string) $file;
    }

    /**
     * Asserts the core leakage invariant (flat mode): no extends, no trait uses.
     */
    public static function assertSelfContained(InterfaceType $interface): void
    {
        if ($interface->getExtends() !== []) {
            throw new \LogicException(sprintf(
                'Derived interface "%s" must not extend anything (adaptee leakage).',
                $interface->getName(),
            ));
        }
    }

    private function deriveName(ReflectionClass $reflection): string
    {
        return self::derivedInterfaceShortName($reflection);
    }

    /**
     * Short name of the interface derived from a class (e.g. Foo => FooInterface).
     */
    public static function derivedInterfaceShortName(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();

        return str_ends_with($shortName, 'Interface')
            ? $shortName . 'Derived'
            : $shortName . 'Interface';
    }

    /**
     * Fully-qualified name of the interface derived from a class.
     */
    public static function derivedInterfaceFqcn(ReflectionClass $reflection): string
    {
        $namespace = $reflection->getNamespaceName();

        return ($namespace === '' ? '' : $namespace . '\\') . self::derivedInterfaceShortName($reflection);
    }
}
