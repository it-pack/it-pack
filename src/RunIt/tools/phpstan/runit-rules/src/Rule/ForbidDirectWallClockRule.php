<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Direct wall-clock access is allowed only inside adapters (and a small,
 * documented set of library clocks). Every other layer must obtain time through
 * the `MonotonicClockInterface` port so durations use monotonic time and the
 * core never depends on the system clock (RunIt AGENTS.md, M13.2).
 *
 * @implements Rule<Node>
 */
final class ForbidDirectWallClockRule extends AbstractSrcRule implements Rule
{
    /**
     * @param class-string<\PhpParser\Node> $nodeType
     * @param list<string>                 $allowDirectories relative paths (under src/) whose files may use the wall clock
     * @param list<string>                 $allowFiles       relative paths (under src/) individually exempted
     */
    public function __construct(
        string $nodeType,
        string $srcRoot,
        private readonly array $allowDirectories,
        private readonly array $allowFiles,
    ) {
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

        if ($this->isAllowed($this->relativePath($scope))) {
            return [];
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $function = strtolower($node->name->toString());
            $clockFunctions = [
                'time', 'microtime', 'date', 'gmdate',
                'mktime', 'gmmktime', 'getdate', 'localtime', 'idate',
            ];

            if (in_array($function, $clockFunctions, true)) {
                return [RuleErrorBuilder::message(sprintf(
                    'Direct wall-clock call %s() is forbidden outside adapters; use the MonotonicClockInterface port (RunIt M13.2).',
                    $function,
                ))->identifier('runit.forbidWallClock.func')->build()];
            }

            return [];
        }

        if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
            $class = $node->class->toString();

            if (in_array($class, ['DateTime', 'DateTimeImmutable', 'DateTimeZone'], true)
                || preg_match('/\\\\(DateTime|DateTimeImmutable|DateTimeZone)$/', '\\' . $class) === 1) {
                return [RuleErrorBuilder::message(sprintf(
                    'Direct wall-clock construction of %s is forbidden outside adapters; use the MonotonicClockInterface port (RunIt M13.2).',
                    $class,
                ))->identifier('runit.forbidWallClock.new')->build()];
            }
        }

        return [];
    }

    private function isAllowed(string $relativePath): bool
    {
        foreach ($this->allowDirectories as $directory) {
            if (str_starts_with($relativePath, rtrim($directory, '/') . '/')) {
                return true;
            }
        }

        return in_array($relativePath, $this->allowFiles, true);
    }
}
