<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Domain artifacts (Execution, Activity, Measurement, Observation, Occurrence,
 * Snapshot and their refinements) are immutable by definition (RunIt ADR-002,
 * M13.2) and therefore must be declared `readonly`. This guards the artifact
 * contracts against accidental mutability.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class RequireReadonlyForArtifactRule extends AbstractSrcRule implements Rule
{
    /**
     * @var list<string>
     */
    private const ARTIFACT_INTERFACES = [
        'ExecutionInterface',
        'ActivityInterface',
        'MeasurementInterface',
        'ObservationInterface',
        'OccurrenceInterface',
        'SnapshotInterface',
        'ExecutionSnapshotInterface',
    ];

    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Class_>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if ($node->isReadonly()) {
            return [];
        }

        if (!str_starts_with($this->relativePath($scope), 'src/Domain/')) {
            return [];
        }

        $implemented = [];

        foreach ($node->implements as $name) {
            $implemented[] = $name->toString();
        }

        $matched = array_values(array_intersect($implemented, self::ARTIFACT_INTERFACES));

        if ($matched === []) {
            return [];
        }

        return [RuleErrorBuilder::message(sprintf(
            'Domain artifact %s implements %s and must be declared readonly (RunIt M13.2).',
            $node->name instanceof Node\Identifier ? $node->name->name : 'anonymous',
            implode(', ', $matched),
        ))->identifier('runit.requireReadonlyArtifact')->build()];
    }
}
