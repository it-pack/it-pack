<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * The scope in which configuration is resolved.
 *
 * Contexts isolate configuration (application, environment, module, package,
 * plugin, tenant, workspace, feature). Aligned with docs/EXECUTION.md
 * "Execution Context".
 *
 * @see docs/architecture/MODEL.md "Configuration Context"
 */
final readonly class ExecutionContext implements ExecutionContextInterface
{
    /**
     * @param array<string, string> $attributes arbitrary isolation attributes (tenant, workspace, ...).
     */
    public function __construct(
        private string $environment = 'production',
        private array $attributes = [],
    ) {
    }

    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function attribute(string $key): ?string
    {
        return $this->attributes[$key] ?? null;
    }
}
