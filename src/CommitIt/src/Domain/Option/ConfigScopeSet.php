<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

use ItPack\CommitIt\Domain\Value\ConfigScope;

/**
 * Shared helper for scope-keyed configuration assignment used by
 * {@see InitOptions} and {@see CloneOptions}.
 *
 * @internal shared option-builder helper; not part of the public contract.
 */
final readonly class ConfigScopeSet
{
    /**
     * Returns a new config array with the given scope's config replaced.
     *
     * @param array<string, array<string, string>> $config Existing config keyed by scope value string.
     * @param array<string, string> $newConfig New config key/value pairs for the scope.
     * @return array<string, array<string, string>>
     */
    public static function withConfig(array $config, array $newConfig, ConfigScope $scope): array
    {
        $merged = $config;
        $merged[$scope->value] = $newConfig;

        return $merged;
    }

    private function __construct()
    {
    }
}
