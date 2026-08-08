<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDescription;
use Arkitect\Analyzer\FullyQualifiedClassName;
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\RuleBuilders\Architecture\Architecture;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\ViolationMessage;
use Arkitect\Rules\Violations;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    // ---------------------------------------------------------------------
    // Layered components (Ports & Adapters) — see docs/REFACTORING_PLAN.md §16.
    // `Contract` / `Core` / `Adapter` / `ValueObject` do not exist as
    // namespaces yet (current code is flat under ItPack\DisenchantIt\); these
    // rules activate as the refactor introduces the directories and turn the
    // gate from dormant to enforcing.
    // ---------------------------------------------------------------------
    $architecture = Architecture::withComponents()
        ->component('Contract')->definedBy('ItPack\DisenchantIt\Contract\*')
        ->component('Core')->definedBy('ItPack\DisenchantIt\Core\*')
        ->component('Adapter')->definedBy('ItPack\DisenchantIt\Adapter\*')
        ->component('ValueObject')->definedBy('ItPack\DisenchantIt\ValueObject\*')
        ->where('Contract')->mayDependOnComponents()                 // nothing internal
        ->where('Core')->mayDependOnComponents('Contract', 'ValueObject')
        ->where('Adapter')->mayDependOnComponents('Contract')        // never Core
        ->where('ValueObject')->mayDependOnComponents();             // nothing internal

    $layeredRules = $architecture->rules();

    // ---------------------------------------------------------------------
    // Rule: interfaces should only depend on interfaces (keeps Contract pure).
    // ---------------------------------------------------------------------
    $interfaceDependencyRule = Rule::allClasses()
        ->that(new IsInterface())
        ->should(new class implements Expression {
            public function describe(ClassDescription $theClass, string $because): Description
            {
                return new Description('interfaces should only depend on interfaces', $because);
            }

            public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
            {
                foreach ($theClass->getDependencies() as $dependency) {
                    $fqcn = $dependency->getFQCN()->toString();

                    if (interface_exists($fqcn) || !class_exists($fqcn)) {
                        continue;
                    }

                    $violations->add(Violation::createWithErrorLine(
                        $theClass->getFQCN(),
                        ViolationMessage::withDescription(
                            $this->describe($theClass, $because),
                            "depends on class {$fqcn}",
                        ),
                        $dependency->getLine(),
                        $theClass->getFilePath(),
                    ));
                }
            }
        })
        ->because('we want clean layered architecture (Contract depends only on interfaces)');

    // ---------------------------------------------------------------------
    // Rule: concrete classes should be final (composition over inheritance).
    // ---------------------------------------------------------------------
    $classesMustBeFinalRule = Rule::namespace('ItPack\DisenchantIt')
        ->should(new readonly class (false) implements Expression {
            public function __construct(private bool $flagExceptions = false)
            {
            }

            public function describe(ClassDescription $theClass, string $because): Description
            {
                return new Description('concrete classes should be final', $because);
            }

            public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
            {
                if ($theClass->isFinal() || $theClass->isInterface() || $theClass->isTrait() || $theClass->isEnum()) {
                    return;
                }

                if (!$this->flagExceptions && is_a($theClass->getFQCN(), Throwable::class, true)) {
                    return;
                }

                $violations->add(Violation::create(
                    $theClass->getFQCN(),
                    ViolationMessage::selfExplanatory($this->describe($theClass, $because)),
                    $theClass->getFilePath(),
                ));
            }
        })
        ->because('we want to enforce composition over inheritance');

    // ---------------------------------------------------------------------
    // Rule: final classes must be backed by an interface (mockable / ports).
    // This is what makes the hexagonal split real — every concrete adapter,
    // strategy, factory and collector implements a port.
    // ---------------------------------------------------------------------
    $finalClassesMustBeBackedRule = Rule::allClasses()
        ->that(new IsFinal())
        ->should(new readonly class (false, false, false) implements Expression {
            public function __construct(
                private bool $flagStaticMethods = false,
                private bool $flagExceptions = false,
                private bool $flagEnums = false,
            ) {
            }

            public function describe(ClassDescription $theClass, string $because): Description
            {
                return new Description('final classes should be backed by an interface', $because);
            }

            public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
            {
                if ($theClass->isInterface() || $theClass->isTrait()) {
                    return;
                }

                if (!$this->flagExceptions && is_a($theClass->getFQCN(), Throwable::class, true)) {
                    return;
                }

                if (!$this->flagEnums && $theClass->isEnum()) {
                    return;
                }

                $classMethods = array_unique(array_merge(
                    get_class_methods($theClass->getFQCN()),
                    ...array_map(
                        static fn (string $ancestor) => get_class_methods($ancestor),
                        array_map(
                            static fn (FullyQualifiedClassName $fqcn) => $fqcn->toString(),
                            $theClass->getExtends(),
                        ),
                    ),
                ), SORT_REGULAR);

                $interfaceMethods = array_unique(array_merge(...array_map(
                    static fn (string $interface) => get_class_methods($interface),
                    array_map(
                        static fn (FullyQualifiedClassName $fqcn) => $fqcn->toString(),
                        $theClass->getInterfaces(),
                    ),
                )));

                $reflectionClass = new ReflectionClass($theClass->getFQCN());

                foreach ($classMethods as $classMethod) {
                    if (str_starts_with($classMethod, '__')) {
                        continue;
                    }

                    if (!$this->flagStaticMethods && $reflectionClass->getMethod($classMethod)->isStatic()) {
                        continue;
                    }

                    if (!in_array($classMethod, $interfaceMethods, true)) {
                        $violations->add(Violation::create(
                            $theClass->getFQCN(),
                            ViolationMessage::withDescription(
                                $this->describe($theClass, $because),
                                "implements no interface for {$classMethod}",
                            ),
                            $theClass->getFilePath(),
                        ));
                    }
                }
            }
        })
        ->because('we want all classes to be mockable behind a port');

    // ---------------------------------------------------------------------
    // Rule: factories are final (and, per REFACTORING_PLAN §0, instantiated
    // and expose build(); the no-static-product-create discipline is enforced
    // by review until an Expression is added).
    // ---------------------------------------------------------------------
    $factoryFinalRule = Rule::allClasses()
        ->that(new HaveNameMatching('*Factory'))
        ->should(new IsFinal())
        ->because('factories are final and composed, never subclassed');

    // ---------------------------------------------------------------------
    // Rule: Core must never depend on RunIt (future base execution layer).
    // DisenchantIt stays RunIt-free; RunIt is wired in later via a bridge
    // package (REFACTORING_PLAN §17 / ADR-0005).
    // ---------------------------------------------------------------------
    $coreNotRunItRule = Rule::namespace('ItPack\DisenchantIt\Core')
        ->should(new NotDependsOnTheseNamespaces(['ItPack\RunIt\*']))
        ->because('Core must not depend on the future RunIt execution layer');

    $config->add(
        $classSet,
        ...[
            ...$layeredRules,
            $interfaceDependencyRule,
            $classesMustBeFinalRule,
            $finalClassesMustBeBackedRule,
            $factoryFinalRule,
            $coreNotRunItRule,
        ],
    );
};
