<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\GitException;

/**
 * Single shared wrapper that converts a {@see GitException} raised by the
 * czproject/git-php adapter into the vendor-neutral CommitIt exception taxonomy.
 *
 * Both git adapter classes delegate their `try { ... } catch (GitException)` to
 * this helper so the mapping logic lives in exactly one place (see REVIEW.md F8).
 * The original {@see GitException} is always preserved as the `previous`
 * throwable inside the mapped exception.
 *
 * @internal adapter-internal helper; not part of the public contract.
 */
final readonly class GitExceptionRunner
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function run(callable $operation, ?string $context = null): mixed
    {
        try {
            return $operation();
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, $context);
        }
    }
}
