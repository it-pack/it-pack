<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Sketch / next-PoC-step for DisenchantIt's detection half.
 *
 * Hard rule this file is built around: DisenchantIt must NEVER `require`,
 * `include`, `class_exists()`, or otherwise execute/autoload the PHP files it
 * analyzes or rewrites. Everything here is pure source-text-in,
 * data-structure-out. This is what makes it safe to run against a class that
 * may already be loaded elsewhere in the same process, and it's the
 * precondition for later doing the actual rewrite without risking a
 * "Cannot redeclare class" fatal.
 *
 * Verification that a rewrite didn't break anything belongs in a SEPARATE
 * process (e.g. `php -l`, or shelling out to run the test suite against the
 * working tree) — never in this one.
 *
 * Requires: nikic/php-parser ^5.0
 */
final class Detector
{
    public function __construct(
        private readonly RoleClassifier $classifier = new RoleClassifier(),
    ) {
    }

    /**
     * @return list<StringLiteral> every string literal found, unfiltered —
     *         classification is attached, filtering is the caller's job.
     */
    public function scanSource(string $code): array
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForNewestSupportedVersion();

        $ast = $parser->parse($code);
        if ($ast === null) {
            return [];
        }

        $traverser = new NodeTraverser();
        // NodeConnectingVisitor populates getAttribute('parent') for every
        // node, which is what RoleClassifier relies on above.
        $traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor());

        $visitor = new MagicStringVisitor($this->classifier);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getFound();
    }

    /**
     * Applies the "is this even a candidate" filter discussed with Tydal:
     * skip exception messages and sprintf-format strings by default: skip
     * anything that doesn't look identifier-shaped. What's left is the
     * "good candidate" bucket worth turning into a constant.
     *
     * @param list<StringLiteral> $literals
     * @return list<StringLiteral>
     */
    public function filterCandidates(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            fn (StringLiteral $lit): bool =>
                // A backed enum case's value (`case PHP = 'php';`) IS
                // already the constant — PHP enums forbid a const and a
                // case sharing a name in the same enum, so promoting one
                // to a *separate* const doesn't just duplicate the value,
                // it fatals ("Cannot redefine class constant").
                $lit->role !== 'enum_backing_value'
                && !in_array($lit->role, ['exception_message', 'format_string'], true)
                && $this->classifier->looksIdentifierLike($lit->value),
        ));
    }

    /**
     * Groups candidates by literal value so repetition (the strongest
     * "this is secretly an enum member" signal) is visible before deciding
     * what to name/generate.
     *
     * @param list<StringLiteral> $candidates
     * @return array<string, list<StringLiteral>>
     */
    public function groupByValue(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $literal) {
            $groups[$literal->value][] = $literal;
        }

        return $groups;
    }

    /**
     * @param string $value the literal being replaced.
     * @param string|null $fallbackHint used when $value has no alphanumeric
     *        content to build a name from at all (e.g. a bare "." or "/"
     *        separator) — typically the class or role name, so the caller
     *        still gets a valid, non-empty constant name rather than a
     *        crash inside the generator library.
     */
    public static function suggestConstantName(string $value, ?string $fallbackHint = null): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? $value;
        $name = strtoupper(trim($slug, '_'));

        if ($name !== '') {
            // Constants can't start with a digit either.
            return ctype_digit($name[0]) ? 'V_' . $name : $name;
        }

        if ($fallbackHint !== null) {
            $hintSlug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $fallbackHint) ?? $fallbackHint);
            return trim($hintSlug, '_') . '_SEPARATOR';
        }

        // Last resort: name it after the value's own character codes so it's
        // at least deterministic and non-empty, rather than throwing.
        return 'CHAR_' . implode('_', array_map('ord', str_split($value)));
    }
}
