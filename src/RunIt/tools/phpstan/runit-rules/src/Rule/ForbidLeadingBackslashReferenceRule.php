<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Forbids leading-backslash fully-qualified name references outside `use`
 * imports (RunIt T1.2 / T2.2). PHPStan's NameResolver rewrites every resolved
 * name into a `Name\FullyQualified` node, so the node type alone cannot tell us
 * whether the source literally wrote `\Foo\Bar`. We therefore inspect the
 * original source token: a name written as `\Foo\Bar` begins at a `\`, whereas a
 * name resolved from a `use` import or a relative reference begins at a letter.
 * References that literally start with a backslash must be shortened to a `use`
 * import so the dependency surface stays explicit and the review scanner
 * (REVIEW R-1) cannot regrow noise.
 *
 * Allowed by exception:
 *  - ordinary `use` imports are naturally excluded, because a name written as
 *    `use Foo\Bar;` has no leading backslash in the source;
 *  - files listed in `$exemptFiles`, e.g. the two Swoole runtime probes that
 *    must `class_exists`-probe an optional extension without triggering autoload
 *    (see REVIEW R-1).
 *
 * Registered for both the `src` and `tests` roots.
 *
 * @implements Rule<Node\Name\FullyQualified>
 */
final class ForbidLeadingBackslashReferenceRule extends AbstractSrcRule implements Rule
{
    /**
     * @param list<string> $exemptFiles relative paths (rooted at `src/` or `tests/`)
     */
    public function __construct(string $srcRoot, private readonly array $exemptFiles = [])
    {
        parent::__construct($srcRoot);
    }

    public function getNodeType(): string
    {
        return Node\Name\FullyQualified::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if (in_array($this->relativePath($scope), $this->exemptFiles, true)) {
            return [];
        }

        // PHPStan's NameResolver rewrites every resolved name into a
        // FullyQualified node, so the node type alone cannot tell us whether the
        // source literally wrote a leading backslash. Inspect the original token:
        // a name written as `\Foo\Bar` begins at a `\` in the source, whereas a
        // name resolved from a `use` import or a relative reference begins at a
        // letter.
        $start = $node->getStartFilePos();

        if ($start < 0) {
            return [];
        }

        $source = @file_get_contents($scope->getFile());

        if ($source === false || ($source[$start] ?? '') !== '\\') {
            return [];
        }

        $name = $node->toString();

        $message = sprintf(
            'Leading-backslash reference "%s" must be imported via a `use` statement '
            . 'instead of being inlined (RunIt T2.2).',
            $name,
        );

        return [RuleErrorBuilder::message($message)
            ->identifier('runit.forbidLeadingBackslashReference')
            ->build()];
    }
}
