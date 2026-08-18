<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\TraverserFactoryInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

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
final readonly class Detector implements StringLiteralDetectorInterface
{
    public function __construct(
        private readonly RoleClassifierInterface $classifier,
        private readonly ParserFactoryInterface $parsers,
        private readonly TraverserFactoryInterface $traversers,
        private readonly MagicStringVisitorFactoryInterface $visitorFactory,
    ) {
    }

    /**
     * @return list<StringLiteralInterface> every string literal found, unfiltered —
     *         classification is attached, filtering is the caller's job.
     */
    public function scanSource(string $code): array
    {
        $parser = $this->parsers->build();
        $ast = $parser->parse($code);
        if ($ast === null) {
            return [];
        }

        $traverser = $this->traversers->build();
        $visitor = $this->visitorFactory->build($this->classifier);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getFound();
    }
}
