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
 * The locale is pinned by merging `LC_ALL=C`/`LANG=C` into the per-process `$env`
 * array forwarded to {@see IRunner::run} — not via a process-global `putenv()`.
 * Because {@see \CzProject\GitPhp\Runners\CliRunner} forwards `$env` to `proc_open`
 * as the child's *entire* environment, we merge the overrides onto a copy of the
 * current process environment so git still resolves PATH, HOME, GIT_*, and global
 * config. Keeping the mutation inside the env array means concurrent git
 * invocations in shared workers can no longer race on the global `LC_ALL`/`LANG`
 * (see REVIEW.md M1).
 *
 * Test runners such as `MemoryRunner` are unaffected because they never spawn a
 * process and ignore the `$env` argument.
 *
 * @internal adapter-internal helper; not part of the public contract.
 */
final class LocalePinningRunner implements IRunner
{
    private const LOCALE = 'C';

    public function __construct(private IRunner $inner)
    {
    }

    /**
     * @param array<string, scalar>|null $env
     */
    public function run($cwd, array $args, ?array $env = null): RunnerResult
    {
        /** @var array<string, string> $processEnv */
        $processEnv = getenv();

        if ($env !== null) {
            foreach ($env as $key => $value) {
                $processEnv[$key] = (string) $value;
            }
        }

        $processEnv['LC_ALL'] = self::LOCALE;
        $processEnv['LANG'] = self::LOCALE;

        return $this->inner->run($cwd, $args, $processEnv);
    }

    public function getCwd(): string
    {
        return $this->inner->getCwd();
    }
}
