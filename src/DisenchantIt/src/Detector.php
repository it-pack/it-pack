<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\ParsedSourceCacheInterface;
use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\Contract\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\TraverserFactoryInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;

/**
 * Detection half of DisenchantIt's pipeline.
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
 * Performance (Phase P4): the nikic parser is built lazily on the first
 * scanSource and reused for every subsequent scan of this Detector's lifetime
 * (the parser is the expensive resource; the traverser+visitor are cheap and
 * rebuilt per scan so visitors never accumulate). The parsed scan result is
 * memoized per SourceCode in a non-owning WeakMap cache, so re-scanning the
 * same SourceCode object — e.g. across detect → rewrite → SemConv — parses the
 * source exactly once.
 *
 * Requires: nikic/php-parser ^5.0
 */
final class Detector implements StringLiteralDetectorInterface
{
    /**
     * Lazily-built, reused nikic parser. Typed `object` (with a docblock) on
     * purpose: it is a vendor instance held privately, never surfaced in a
     * constructor/return signature, so Core stays free of vendor type
     * dependencies in its public API.
     *
     * @var \PhpParser\Parser|null
     */
    private ?object $parser = null;

    public function __construct(
        private readonly RoleClassifierInterface $classifier,
        private readonly ParserFactoryInterface $parsers,
        private readonly TraverserFactoryInterface $traversers,
        private readonly MagicStringVisitorFactoryInterface $visitorFactory,
        private readonly ParsedSourceCacheInterface $cache,
    ) {
    }

    /**
     * @return list<LiteralInterface> every literal found, unfiltered —
     *         classification is attached, filtering is the caller's job.
     */
    public function scanSource(SourceCodeInterface $code): array
    {
        $cached = $this->cache->get($code);
        if ($cached !== null) {
            return $cached;
        }

        if ($this->parser === null) {
            $this->parser = $this->parsers->build();
        }

        /** @var \PhpParser\Parser $parser */
        $parser = $this->parser;

        /** @var array<\PhpParser\Node\Stmt>|null $ast */
        $ast = $parser->parse($code->code);
        if ($ast === null) {
            return [];
        }

        // A fresh traverser + visitor per scan: the traverser accumulates
        // visitors, so reusing it would stack MagicLiteralVisitor instances
        // across scans. The parser above is the expensive part and is reused.
        $traverser = $this->traversers->build();
        $visitor = $this->visitorFactory->build($this->classifier, $code->code);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        /** @var list<LiteralInterface> $found */
        $found = $visitor->getFound();
        $this->cache->put($code, $found);

        return $found;
    }

    /**
     * @return \Generator<int, LiteralInterface, void, void>
     */
    public function scanSourceStreaming(SourceCodeInterface $code): \Generator
    {
        yield from $this->scanSource($code);
    }
}
