<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * Definition of a single feature flag, as declared in configuration.
 *
 * A flag is evaluated against an {@see ExecutionContextInterface} (environment,
 * tenant, user, and arbitrary attributes). Flags are intentionally a *thin,
 * schema-driven evaluation layer* built on top of the resolved configuration —
 * they introduce no new source of truth (see docs/ADDITIONAL_FEATURES_PLAN.md §8).
 *
 * Evaluation order:
 *   1. master switch (`enabled`): when false the flag is off for everyone;
 *   2. environment allow-list (`environments`): if non-empty, the flag is on
 *      only in the listed environments;
 *   3. tenant allow-list (`tenants`): if non-empty, the flag is on only for the
 *      listed tenant attribute values;
 *   4. attribute match (`attributes`): every key/value must match a context
 *      attribute;
 *   5. rollout (`rollout`, 0–100): a stable percentage of the bucketing unit
 *      (see `bucketBy`) receives the flag.
 */
final readonly class FlagDefinition
{
    /**
     * @param list<string> $environments
     * @param list<string> $tenants
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $name,
        public bool $enabled = false,
        public array $environments = [],
        public array $tenants = [],
        public array $attributes = [],
        public int $rollout = 100,
        public string $bucketBy = 'tenant',
    ) {
    }

    /**
     * @param mixed $data
     */
    public static function fromArray(string $name, mixed $data): self
    {
        if (\is_bool($data)) {
            return new self($name, $data);
        }

        if (!\is_array($data)) {
            throw new \InvalidArgumentException(
                \sprintf('Feature flag "%s" must be a bool or an array definition, %s given.', $name, \gettype($data)),
            );
        }

        $environments = self::stringList($data['environments'] ?? []);
        $tenants = self::stringList($data['tenants'] ?? []);
        $attributes = self::stringMap($data['attributes'] ?? []);

        $bucketBy = $data['bucketBy'] ?? 'tenant';

        if (!\is_string($bucketBy) || !\in_array($bucketBy, ['tenant', 'user', 'environment'], true)) {
            throw new \InvalidArgumentException(
                \sprintf('Feature flag "%s" has an invalid bucketBy "%s".', $name, \gettype($bucketBy)),
            );
        }

        return new self(
            name: $name,
            enabled: (bool) ($data['enabled'] ?? false),
            environments: $environments,
            tenants: $tenants,
            attributes: $attributes,
            rollout: self::normalizeRollout($name, $data['rollout'] ?? 100),
            bucketBy: $bucketBy,
        );
    }

    public function isEnabledFor(ExecutionContextInterface $ctx): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->environments !== [] && !\in_array($ctx->environment(), $this->environments, true)) {
            return false;
        }

        if ($this->tenants !== [] && !$this->tenantMatch($ctx)) {
            return false;
        }

        foreach ($this->attributes as $key => $expected) {
            if ($ctx->attribute($key) !== $expected) {
                return false;
            }
        }

        return $this->rolloutHit($ctx);
    }

    private function tenantMatch(ExecutionContextInterface $ctx): bool
    {
        $tenant = $ctx->attribute('tenant');

        return $tenant !== null && \in_array($tenant, $this->tenants, true);
    }

    private function rolloutHit(ExecutionContextInterface $ctx): bool
    {
        if ($this->rollout >= 100) {
            return true;
        }

        if ($this->rollout <= 0) {
            return false;
        }

        $bucket = match ($this->bucketBy) {
            'user' => $ctx->attribute('user'),
            'environment' => $ctx->environment(),
            default => $ctx->attribute('tenant') ?? $ctx->environment(),
        };

        if ($bucket === null) {
            return false;
        }

        // Stable, order-independent bucket: the same (unit, flag) always maps to
        // the same percentage, so a user/tenant keeps a consistent experience.
        $percent = \crc32($bucket . "\0" . $this->name) % 100;

        return $percent < $this->rollout;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Flag environments/tenants must be a list of strings.');
        }

        $out = [];

        foreach ($value as $item) {
            if (!\is_scalar($item) && $item !== null) {
                throw new \InvalidArgumentException('Flag environments/tenants must be a list of scalars.');
            }

            $out[] = \strval($item);
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Flag attributes must be a map of string => string.');
        }

        $map = [];

        foreach ($value as $key => $item) {
            // Array keys are always int|string; only the value needs a scalar
            // guard before strval (which rejects mixed).
            if (!\is_scalar($item) && $item !== null) {
                throw new \InvalidArgumentException('Flag attribute values must be scalars.');
            }

            $map[\strval($key)] = \strval($item);
        }

        return $map;
    }

    private static function normalizeRollout(string $name, mixed $value): int
    {
        if (!\is_numeric($value)) {
            throw new \InvalidArgumentException(
                \sprintf('Feature flag "%s" rollout must be numeric, %s given.', $name, \gettype($value)),
            );
        }

        $rollout = \intval($value);

        if ($rollout < 0 || $rollout > 100) {
            throw new \InvalidArgumentException(
                \sprintf('Feature flag "%s" rollout must be between 0 and 100, %d given.', $name, $rollout),
            );
        }

        return $rollout;
    }
}
