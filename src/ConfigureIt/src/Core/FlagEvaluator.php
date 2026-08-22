<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Contract\FlagEvaluatorInterface;
use ItPack\ConfigureIt\ValueObject\FlagDefinition;

/**
 * Evaluates feature flags declared in the resolved configuration.
 *
 * The evaluator is a thin, schema-driven layer built on top of the resolved
 * snapshot — it introduces no new source of truth (see
 * docs/ADDITIONAL_FEATURES_PLAN.md §8). Flag definitions are read from a single
 * key (default `feature_flags`) and combined with an
 * {@see ExecutionContextInterface} (environment, tenant, user, attributes) to
 * decide whether each flag is on.
 */
final class FlagEvaluator implements FlagEvaluatorInterface
{
    /**
     * @param array<string, FlagDefinition> $definitions
     */
    /** @var array<string, FlagDefinition> */
    private array $definitions;

    public function __construct(
        ConfigurationSnapshotInterface $snapshot,
        string $path = 'feature_flags',
    ) {
        $this->definitions = self::build($snapshot, $path);
    }

    /**
     * @return array<string, FlagDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function has(string $flag): bool
    {
        return isset($this->definitions[$flag]);
    }

    public function isEnabled(string $flag, ExecutionContextInterface $ctx): bool
    {
        $definition = $this->definitions[$flag] ?? null;

        return $definition !== null && $definition->isEnabledFor($ctx);
    }

    public function evaluate(ExecutionContextInterface $ctx): array
    {
        $states = [];

        foreach ($this->definitions as $name => $definition) {
            $states[$name] = $definition->isEnabledFor($ctx);
        }

        return $states;
    }

    /**
     * @return array<string, FlagDefinition>
     */
    private static function build(ConfigurationSnapshotInterface $snapshot, string $path): array
    {
        if (!$snapshot->has($path)) {
            return [];
        }

        $raw = $snapshot->get($path);

        if (!\is_array($raw)) {
            throw new \LogicException(
                \sprintf('Feature flags at "%s" must resolve to a map of flag definitions.', $path),
            );
        }

        $definitions = [];

        foreach ($raw as $name => $data) {
            if (!\is_string($name)) {
                continue;
            }

            $definitions[$name] = FlagDefinition::fromArray($name, $data);
        }

        return $definitions;
    }
}
