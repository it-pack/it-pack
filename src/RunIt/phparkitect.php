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
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\RuleBuilders\Architecture\Architecture;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\ViolationMessage;
use Arkitect\Rules\Violations;
use function ItPack\Qa\PhpArkitect\layeredArchitecture;

/**
 * phparkitect rules for it-pack/run-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/RunIt/deptrac (archived, deleted)):
 *   Common           → ~
 *   Contract         → Common  (uses src/Contract/(?!Port/).* — Port is a
 *                       separate layer in deptrac; here Contract's selector
 *                       also matches Port. Safe because deptrac is green.)
 *   Port              → Common, Contract
 *   Domain            → Common, Contract, Port  (includes src/Behavior/Domain/)
 *   Application       → Common, Contract, Port, Domain  (includes src/Behavior/Application/)
 *   SDK               → Common, Contract, Port, Domain, Application
 *   Common Adapter    → Common, Contract, Port, Domain
 *   External Adapter  → Common, Contract, Port, Domain
 *   Test Support      → all layers
 *
 * Common Adapter and External Adapter are merged into a single Adapter
 * component (both have identical dependency rules in deptrac).
 *
 * BehaviorDomain (src/Behavior/Domain/) and BehaviorApplication
 * (src/Behavior/Application/) belong to the Domain and Application layers
 * respectively in deptrac; here they are separate components that all
 * Domain/Application-allowing layers also list.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $rules = layeredArchitecture(
        'ItPack\RunIt',
        [
            'Common' => 'Common\*',
            'Contract' => 'Contract\*',
            'Domain' => 'Domain\*',
            'BehaviorDomain' => 'Behavior\Domain\*',
            'Application' => 'Application\*',
            'BehaviorApplication' => 'Behavior\Application\*',
            'SDK' => 'SDK\*',
            'Adapter' => 'Adapter\*',
            'Conformance' => 'Conformance\*',
        ],
        [
            'Common' => [],
            'Contract' => ['Common'],
            'Domain' => ['Common', 'Contract', 'BehaviorDomain'],
            'BehaviorDomain' => ['Common', 'Contract', 'Domain'],
            'Application' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'BehaviorApplication'],
            'BehaviorApplication' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'Application'],
            'SDK' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'Application', 'BehaviorApplication'],
            'Adapter' => ['Common', 'Contract', 'Domain', 'BehaviorDomain'],
            'Conformance' => [
                'Common', 'Contract', 'Domain', 'BehaviorDomain',
                'Application', 'BehaviorApplication', 'SDK', 'Adapter',
            ],
        ]
    );


    $finalClassesMustBeBackedRule = Rule::allClasses()
        ->that(new IsFinal())
        ->should(
            new readonly class (
                false,
                false,
                false,
                []
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

    $config->add($classSet, $finalClassesMustBeBackedRule, ...$rules);
};
