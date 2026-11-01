<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Analyzer\FullyQualifiedClassName;
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotResideInTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespacesExactly;
use Arkitect\RuleBuilders\Architecture\Architecture;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\ViolationMessage;
use Arkitect\Rules\Violations;


/**
 * phparkitect rules for it-pack/async-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/AsyncIt/deptrac (archived, deleted)):
 *   Common       → ~
 *   Contract     → [Common]  (excludes Contract\Port\*)
 *   Port          → [Common, Contract, Domain]
 *   Domain        → [Common, Contract, Port]
 *   Application   → [Common, Contract, Port, Domain]
 *   SDK           → [Common, Contract, Port, Domain, Application]
 *   Adapter       → [Common, Contract, Port, Domain]
 *   Test Support  → [all]
 *
 * The deptrac Contract collector uses a negative-lookahead regex
 * `src/Contract/(?!Port/).*` so Port classes belong to a separate layer.
 * phparkitect cannot express negative namespace globs, so:
 *   - Port is its own component with selector `Contract\Port\*`.
 *   - Contract (excl Port) is enforced via a separate Rule using
 *     `ResideInOneOfTheseNamespacesExactly('ItPack\AsyncIt\Contract')`,
 *     which matches only classes whose namespace is exactly `Contract`
 *     (the directory has no sub-namespaces other than Port).
 *   - Contract is not a component in the Architecture DSL, so Contract
 *     dependencies are not forbidden by any component rule — matching
 *     deptrac's allow-all-unless-forbidden semantics.
 *
 * Application → Adapter: The assembly factories in
 * Application\Factory (SwooleAssemblyFactory, SwowAssemblyFactory,
 * RevoltAssemblyFactory) compose-adapter classes. This is the
 * composition root for adapter assembly — the reverse direction
 * (Adapter → Application) is forbidden. See
 * docs/IMPLEMENTATION_PLAN.md §1 for rationale.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $namespace = 'ItPack\AsyncIt';

    $architecture = Architecture::withComponents()
        ->component('Common')->definedBy('ItPack\AsyncIt\Common\*')
        ->component('Domain')->definedBy('ItPack\AsyncIt\Domain\*')
        ->component('Port')->definedBy('ItPack\AsyncIt\Contract\Port\*')
        ->component('Application')->definedBy('ItPack\AsyncIt\Application\*')
        ->component('SDK')->definedBy('ItPack\AsyncIt\SDK\*')
        ->component('Adapter')->definedBy('ItPack\AsyncIt\Adapter\*')
        ->component('Conformance')->definedBy('ItPack\AsyncIt\Conformance\*')
        ->where('Common')->mayDependOnComponents()
        ->where('Domain')->mayDependOnComponents('Common', 'Port')
        ->where('Port')->mayDependOnComponents('Common', 'Domain')
        ->where('Application')->mayDependOnComponents('Common', 'Domain', 'Port', 'Adapter')
        ->where('SDK')->mayDependOnComponents('Common', 'Domain', 'Port', 'Adapter', 'Application')
        ->where('Adapter')->mayDependOnComponents('Common', 'Domain', 'Port')
        ->where('Conformance')->mayDependOnComponents('Common', 'Domain', 'Port', 'Application', 'SDK', 'Adapter');

    $rules = array_merge(
        iterator_to_array($architecture->rules()),
        [
            // Contract (excl Port) may depend on Port (co-located value types,
            // vocabulary interfaces), but must not reach into Domain or higher.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespacesExactly('ItPack\AsyncIt\Contract'))
                ->should(new NotDependsOnTheseNamespaces([
                    'ItPack\AsyncIt\Domain',
                    'ItPack\AsyncIt\Application',
                    'ItPack\AsyncIt\SDK',
                    'ItPack\AsyncIt\Adapter',
                    'ItPack\AsyncIt\Conformance',
                ]))
                ->because('Contract must not depend on Domain or higher layers (except Port/Common)'),
        ]
    );

    $interfaceDependencyRule = Rule::allClasses()
        ->that(new IsInterface())
        ->andThat(
            new NotResideInTheseNamespaces(
                sprintf('%s\\Infrastructure\\*', $namespace)
            )
        )
        ->should(
            new class implements Expression {

                public function describe(ClassDescription $theClass, string $because): Description
                {
                    return new Description(
                        "interfaces should only depend on interfaces", $because
                    );
                }

                public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
                {
                    foreach ($theClass->getDependencies() as $dependency) {
                        if(
                            interface_exists($dependency->getFQCN()->toString())
                            || !class_exists($dependency->getFQCN()->toString())
                            || enum_exists($dependency->getFQCN()->toString())
                        ) {
                            continue;
                        }

                        $violations->add(
                            Violation::createWithErrorLine(
                                $theClass->getFQCN(),
                                ViolationMessage::withDescription(
                                    $this->describe($theClass, $because),
                                    "depends on class {$dependency->getFQCN()->toString()}"
                                ),
                                $dependency->getLine(),
                                $theClass->getFilePath()
                            )
                        );
                    }
                }
            }

        )
        ->because("we want clean layered architecture");


    $classesMustBeFinalRule = Rule::namespace($namespace)
        ->should(
            new readonly class (false) implements Expression {

                public function __construct(private bool $flagExceptions = false){}

                public function describe(ClassDescription $theClass, string $because): Description
                {
                    return new Description(
                        "should be be final", $because
                    );
                }

                public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
                {
                    if(
                        $theClass->isFinal()
                        || $theClass->isInterface()
                        || $theClass->isTrait()
                        || $theClass->isEnum()
                    ) {
                        return;
                    }

                    if(!$this->flagExceptions && is_a($theClass->getFQCN(), Throwable::class, true)) {
                        return;
                    }

                    $violations->add(
                        Violation::create(
                            $theClass->getFQCN(),
                            ViolationMessage::selfExplanatory(
                                $this->describe($theClass, $because),
                            ),
                            $theClass->getFilePath()
                        )
                    );
                }
            }

        )
        ->because("we want to enforce composition over inheritance");

    $finalClassesMustBeBackedRule = Rule::allClasses()
        ->that(new IsFinal())
        ->should(
            new readonly class (
                false,
                false,
                false,
            ) implements Expression {

                public function __construct(
                    private bool $flagStaticMethods = false,
                    private bool $flagExceptions = false,
                    private bool $flagEnums = false,
                    private array $treatedAsInterfaces = [],
                ){}

                public function describe(ClassDescription $theClass, string $because): Description
                {
                    return new Description(
                        "all methods should be backed by interfaces", $because
                    );
                }

                public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
                {
                    if(
                        $theClass->isInterface() || $theClass->isTrait()
                    ) {
                        return;
                    }

                    if(!$this->flagExceptions && is_a($theClass->getFQCN(), Throwable::class, true)) {
                        return;
                    }

                    if(!$this->flagEnums && $theClass->isEnum()) {
                        return;
                    }

                    $classMethods = array_unique(
                        array_merge(
                            get_class_methods($theClass->getFQCN()),
                            ...array_map(
                                static fn (string $ancestor) => get_class_methods($ancestor),
                                array_map(
                                    static fn(FullyQualifiedClassName $fqcn) => $fqcn->toString(),
                                    $theClass->getExtends()
                                )
                            )
                        ),
                        SORT_REGULAR
                    );

                    $interfaceMethods = array_unique(
                        array_merge(
                            ...array_map(
                                static fn (string $interface) => get_class_methods($interface),
                                array_map(
                                    static fn(FullyQualifiedClassName $fqcn) => $fqcn->toString(),
                                    $theClass->getInterfaces()
                                )
                            )
                        )
                    );

                    foreach ($this->treatedAsInterfaces as $treatedAsInterface) {
                        if (class_exists($treatedAsInterface) || interface_exists($treatedAsInterface)) {
                            $interfaceMethods = array_merge(
                                $interfaceMethods,
                                get_class_methods($treatedAsInterface)
                            );
                        }
                    }

                    $reflectionClass = new ReflectionClass($theClass->getFQCN());

                    foreach ($classMethods as $classMethod) {
                        if(str_starts_with($classMethod, '__')) {
                            continue;
                        }

                        if(!$this->flagStaticMethods && $reflectionClass->getMethod($classMethod)->isStatic()) {
                            continue;
                        }

                        if(!in_array($classMethod, $interfaceMethods, true)) {
                            $violations->add(
                                Violation::create(
                                    $theClass->getFQCN(),
                                    ViolationMessage::withDescription(
                                        $this->describe($theClass, $because),
                                        "implements no interface for {$classMethod}"
                                    ),
                                    $theClass->getFilePath()
                                )
                            );
                        }
                    }
                }
            }

        )
        ->because("we want all classes to be mockable");

    $config->add(
        $classSet,
        $interfaceDependencyRule,
        $classesMustBeFinalRule,
        $finalClassesMustBeBackedRule,
        ...$rules,
    );
};
