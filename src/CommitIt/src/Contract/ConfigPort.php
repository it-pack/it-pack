<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\ConfigScope;
use Stringable;

/**
 * Git configuration management port.
 *
 * Provides read/write access to git configuration settings across all supported
 * scopes (system, global, local, worktree, file). Configuration keys follow
 * git's dot-notation (e.g. `user.name`, `core.autocrlf`).
 *
 * The {@see Repository} interface extends this port, so config management is
 * available on every repository handle.
 *
 * @api
 */
interface ConfigPort
{
    /**
     * Returns all configuration key/value pairs for the given scope.
     *
     * @return array<string, string>
     */
    public function getAll(ConfigScope $scope = ConfigScope::Local): array;

    /**
     * Returns the value of a single configuration key, or null when not set.
     */
    public function get(string|Stringable $key, ConfigScope $scope = ConfigScope::Local): ?string;

    /**
     * Sets a configuration key/value pair for the given scope.
     *
     * When {@see ConfigScope::File} is used, `$file` must point to a valid
     * config file path.
     */
    public function set(
        string|Stringable $key,
        string|Stringable $value,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void;

    /**
     * Removes a configuration key for the given scope.
     *
     * When {@see ConfigScope::File} is used, `$file` must point to a valid
     * config file path.
     */
    public function unset(
        string|Stringable $key,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void;
}
