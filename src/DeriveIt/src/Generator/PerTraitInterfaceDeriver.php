<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\DeriverInterface;
use ItPack\DeriveIt\Reflection\ClassReflection;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;

/**
 * Derives one interface per trait (Interface Segregation applied to the tool) and
 * composes the main interface so it `extends` exactly those derived trait
 * interfaces.
 *
 * Leakage invariant (per-trait mode): the main interface must extend ONLY the
 * derived trait interfaces — never the adaptee's own parent/interface hierarchy.
 * When a method name is declared by multiple traits, the first trait in
 * getTraitsRecursively() order owns it; the others drop it, so no duplicate
 * method declaration can occur across the extended interfaces.
 */
final class PerTraitInterfaceDeriver implements DeriverInterface
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

    /**
     * @return array<string, InterfaceType> keyed by trait short name.
     */
    public function deriveTraitInterfaces(ReflectionClass $reflection): array
    {
        $claimed = [];
        $interfaces = [];

        foreach (ClassReflection::create($reflection->getName())->getTraitsRecursively() as $trait) {
            $methods = [];
            foreach ($this->extractor->extract($trait) as $method) {
                $name = $method->getName();
                if (isset($claimed[$name])) {
                    continue;
                }
                $claimed[$name] = true;
                $methods[] = $this->signatureFactory->fromMethod($method, $this->substitutor);
            }

            if ($methods === []) {
                continue;
            }

            $interface = new InterfaceType($trait->getShortName() . 'Interface');
            $interface->setMethods($methods);
            $interfaces[$trait->getShortName()] = $interface;
        }

        return $interfaces;
    }

    public function derive(ReflectionClass|string $class): InterfaceType
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);

        $main = new InterfaceType($this->deriveName($reflection));
        $main->setExtends(array_map(static fn (InterfaceType $i): string => $i->getName(), array_values($traitInterfaces)));

        $traitMethodNames = [];
        foreach ($traitInterfaces as $interface) {
            foreach ($interface->getMethods() as $method) {
                $traitMethodNames[$method->getName()] = true;
            }
        }

        $mainMethods = [];
        foreach ($this->extractor->extract($reflection) as $method) {
            if (isset($traitMethodNames[$method->getName()])) {
                continue;
            }
            $mainMethods[] = $this->signatureFactory->fromMethod($method, $this->substitutor);
        }
        $main->setMethods($mainMethods);

        self::assertPerTraitSelfContained($main, $traitInterfaces);

        return $main;
    }

    public function deriveSource(ReflectionClass|string $class): string
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
        $traitInterfaces = $this->deriveTraitInterfaces($reflection);
        $main = $this->derive($reflection);

        $namespace = new PhpNamespace($reflection->getNamespaceName());
        foreach ($traitInterfaces as $interface) {
            $namespace->add($interface);
        }
        $namespace->add($main);

        $file = new PhpFile();
        $file->addNamespace($namespace);

        return (string) $file;
    }

    /**
     * Asserts the core leakage invariant (per-trait mode): the main interface
     * extends exactly the derived trait interfaces.
     *
     * @param array<string, InterfaceType> $traitInterfaces
     */
    public static function assertPerTraitSelfContained(InterfaceType $main, array $traitInterfaces): void
    {
        $expected = array_map(static fn (InterfaceType $i): string => $i->getName(), array_values($traitInterfaces));
        sort($expected);

        $actual = $main->getExtends();
        sort($actual);

        if ($expected !== $actual) {
            throw new \LogicException(sprintf(
                'Main interface "%s" must extend exactly the derived trait interfaces (adaptee leakage).',
                $main->getName(),
            ));
        }
    }

    private function deriveName(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();

        return str_ends_with($shortName, 'Interface')
            ? $shortName . 'Derived'
            : $shortName . 'Interface';
    }
}
