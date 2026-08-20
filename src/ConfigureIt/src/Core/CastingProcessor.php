<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\Exception\CastException;

/**
 * Coerces node values to explicitly declared scalar types.
 *
 * Carries a flat dotted-key → type map (`int`, `float`, `bool`, `string`) and
 * rewrites every matching node to its cast value while preserving source,
 * priority and metadata. Cast failures raise {@see CastException} so the
 * pipeline fails fast instead of silently shipping a wrong-typed value. This is
 * the "Type Coercion" pipeline stage; it is the counterpart to
 * {@see DefaultsProcessor} for making untyped sources (INI, ENV, XML) safe.
 *
 * @see docs/architecture/PIPELINE.md "Casting"
 */
final readonly class CastingProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface
{
    /**
     * @param array<string, string> $casts flat dotted-key → target type.
     */
    public function __construct(
        private array $casts,
    ) {
    }

    public function stageName(): string
    {
        return 'CastingProcessor';
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $next = new ConfigurationGraph($graph->nodes());

        foreach ($this->casts as $key => $type) {
            if (!$next->has($key)) {
                continue;
            }

            $node = $next->node($key);
            $next->put($node->withValue($this->cast($node->value(), $type, $key)));
        }

        return $next;
    }

    /**
     * @return int|float|bool|string
     */
    private function cast(mixed $value, string $type, string $key): int|float|bool|string
    {
        return match ($type) {
            'int' => $this->toInt($value, $key),
            'float' => $this->toFloat($value, $key),
            'bool' => $this->toBool($value, $key),
            'string' => $this->toString($value, $key),
            default => throw new CastException(sprintf('Unknown cast type "%s" for key "%s".', $type, $key)),
        };
    }

    private function toInt(mixed $value, string $key): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value)) {
            return (int) $value;
        }

        if (\is_string($value) && \is_numeric($value) && !\str_contains($value, '.')) {
            return (int) $value;
        }

        throw new CastException(sprintf('Cannot cast key "%s" to int.', $key));
    }

    private function toFloat(mixed $value, string $key): float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }

        if (\is_string($value) && \is_numeric($value)) {
            return (float) $value;
        }

        throw new CastException(sprintf('Cannot cast key "%s" to float.', $key));
    }

    private function toBool(mixed $value, string $key): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return $value === 1;
        }

        if (\is_string($value)) {
            return match (\strtolower(\trim($value))) {
                'true', '1', 'on', 'yes', 'y' => true,
                'false', '0', 'off', 'no', 'n' => false,
                default => throw new CastException(sprintf('Cannot cast key "%s" to bool.', $key)),
            };
        }

        throw new CastException(sprintf('Cannot cast key "%s" to bool.', $key));
    }

    private function toString(mixed $value, string $key): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value)) {
            return (string) $value;
        }

        throw new CastException(sprintf('Cannot cast key "%s" to string.', $key));
    }
}
