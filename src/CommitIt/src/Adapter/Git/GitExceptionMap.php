<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\GitException;
use ItPack\CommitIt\Contract\Exception\VcsOperationException as VcsOperationExceptionContract;
use ItPack\CommitIt\Domain\Exception\DirtyWorkingTreeException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;

/**
 * Maps {@see GitException} raised by the czproject/git-php adapter to the
 * vendor-neutral CommitIt exception taxonomy. The original {@see GitException} is
 * always preserved as the `previous` throwable so debugging keeps the real cause.
 *
 * Mapping is message-driven because git-php surfaces backend failures through
 * generic {@see GitException} instances; the exit-code alone is not descriptive
 * enough to classify. The heuristics below target the most common git failure
 * shapes and fall back to {@see VcsOperationException}.
 *
 * @internal adapter-internal mapping; not part of the public contract.
 */
final class GitExceptionMap
{
    /**
     * @return VcsOperationExceptionContract
     */
    public static function map(GitException $exception, ?string $context = null): VcsOperationExceptionContract
    {
        $message = $exception->getMessage();
        $previous = $exception;

        if (self::matchesRepositoryNotFound($message)) {
            return new RepositoryNotFoundException(
                self::prefixed($context, $message),
                0,
                $previous,
            );
        }

        if (self::matchesInvalidReference($message)) {
            return new InvalidReferenceNameException(
                self::prefixed($context, $message),
                0,
                $previous,
            );
        }

        if (self::matchesDirtyTree($message)) {
            return new DirtyWorkingTreeException(
                self::prefixed($context, $message),
                0,
                $previous,
            );
        }

        return new VcsOperationException(
            self::prefixed($context, $message),
            0,
            $previous,
        );
    }

    private static function matchesRepositoryNotFound(string $message): bool
    {
        return self::containsAny($message, [
            'not found',
            'not a git repository',
            'not a valid git repository',
            'does not exist',
            'no such file or directory',
        ]);
    }

    private static function matchesInvalidReference(string $message): bool
    {
        return self::containsAny($message, [
            'not a valid',
            'unknown revision',
            'ambiguous',
            'malformed',
            'invalid reference',
            'bad revision',
            'fatal: bad',
            'does not match',
        ]);
    }

    private static function matchesDirtyTree(string $message): bool
    {
        return self::containsAny($message, [
            'working tree',
            'working directory',
            'uncommitted',
            'not clean',
            'local changes',
            'would be overwritten',
            'please commit',
            'cannot ',
        ]);
    }

    /**
     * @param list<string> $needles
     */
    private static function containsAny(string $message, array $needles): bool
    {
        $lowered = strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($lowered, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private static function prefixed(?string $context, string $message): string
    {
        if ($context === null || $context === '') {
            return $message;
        }

        return $context . ': ' . $message;
    }
}
