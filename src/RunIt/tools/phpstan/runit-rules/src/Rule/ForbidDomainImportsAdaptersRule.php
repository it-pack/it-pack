<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Non-adapter layers must not import adapter implementations. Adapters are the
 * outward boundary of the hexagon; importing them from Domain/Contract/SDK/
 * Application couples the core to concrete integrations (RunIt M13.2, also
 * enforced by DepTrac in M13.3). Adapters may freely import other adapters.
 *
 * @implements Rule<Node>
 */
final class ForbidDomainImportsAdaptersRule extends AbstractSrcRule implements Rule
{
    /**
     * @param class-string<\PhpParser\Node> $nodeType
     */
    public function __construct(string $nodeType, string $srcRoot)
    {
        parent::__construct($srcRoot);
        $this->nodeType = $nodeType;
    }

    /** @var class-string<\PhpParser\Node> */
    private string $nodeType;

    /**
     * @return class-string<\PhpParser\Node>
     */
    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        $relativePath = $this->relativePath($scope);

        if (str_starts_with($relativePath, 'src/Adapter/')) {
            return [];
        }

        $errors = [];

        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->reportIfAdapter($use->name->toString(), $relativePath, $errors);
            }

            return $errors;
        }

        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toString();

            foreach ($node->uses as $use) {
                $this->reportIfAdapter($prefix . '\\' . $use->name->toString(), $relativePath, $errors);
            }
        }

        return $errors;
    }

    /**
     * @param list<\PHPStan\Rules\IdentifierRuleError> $errors
     */
    private function reportIfAdapter(string $fqcn, string $relativePath, array &$errors): void
    {
        if (!str_starts_with($fqcn, 'ItPack\\RunIt\\Adapter\\')) {
            return;
        }

        $errors[] = RuleErrorBuilder::message(sprintf(
            'Library layer %s must not import adapter %s; depend on a port or contract instead (RunIt M13.2).',
            $relativePath,
            $fqcn,
        ))->identifier('runit.forbidAdapterImport')->build();
    }
}
