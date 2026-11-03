<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Git configuration scope, mapping to `git config --scope=<scope>` flags.
 *
 * All scopes supported by `git config` are represented: system, global,
 * local, worktree, and file.
 *
 * @api
 */
enum ConfigScope: string
{
    case System = 'system';
    case Global = 'global';
    case Local = 'local';
    case Worktree = 'worktree';
    case File = 'file';
}
