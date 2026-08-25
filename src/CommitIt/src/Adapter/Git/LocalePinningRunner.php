<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\RunnerResult;

/**
 * Decorates a git {@see IRunner} so every spawned git process runs under a stable
 * `C` locale. CommitIt maps backend failures by matching substrings in git's
 * (English) messages; a localized git would make that heuristic fall through to a
 * generic {@see \ItPack\CommitIt\Domain\Exception\VcsOperationException}. Pinning
 * `LC_ALL`/`LANG` keeps the mapping deterministic regardless of the host locale
 * (see REVIEW.md F6).
 *
 * The locale is pinned via `putenv()` immediately before delegating and restored
 * afterwards. Because `proc_open` inherits the parent process environment when no
 * explicit env array is given (the default for {@see \CzProject\GitPhp\Runners\CliRunner}),
 * the child git process sees `LC_ALL=C` while the host environment (HOME, PATH,
 * GIT_*, ...) is left untouched — so git keeps resolving global config and commit
 * author identity. Test runners such as `MemoryRunner` are unaffected because they
 * never spawn a process.
 *
 * @internal adapter-internal helper; not part of the public contract.
 */
final class LocalePinningRunner implements IRunner
{
    public function __construct(private IRunner $inner)
    {
    }

    public function run($cwd, array $args, ?array $env = null): RunnerResult
    {
        $previousLocale = getenv('LC_ALL');
        $previousLang = getenv('LANG');

        putenv('LC_ALL=C');
        putenv('LANG=C');

        try {
            return $this->inner->run($cwd, $args, $env);
        } finally {
            $this->restore('LC_ALL', $previousLocale);
            $this->restore('LANG', $previousLang);
        }
    }

    public function getCwd(): string
    {
        return $this->inner->getCwd();
    }

    /**
     * @param string|false $previous
     */
    private function restore(string $name, $previous): void
    {
        if ($previous === false) {
            putenv($name);

            return;
        }

        putenv($name . '=' . $previous);
    }
}
