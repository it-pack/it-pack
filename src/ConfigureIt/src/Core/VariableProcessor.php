<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;

/**
 * Resolves `${dotted.key}` placeholders inside string values against the graph.
 *
 * Walks every string node and substitutes `${key}` occurrences with the value of
 * the matching resolved node (scalars only). Unknown references are left verbatim
 * so partial configurations stay parseable. This is the "Variable Resolution"
 * pipeline stage; it is pure (reads the graph, rewrites nodes in place) and
 * deterministic.
 *
 * @see docs/architecture/PIPELINE.md "Variable Resolution"
 */
final readonly class VariableProcessor implements ConfigurationProcessorInterface
{
    private const PATTERN = '/\$\{([a-zA-Z0-9_.]+)\}/';

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $next = new ConfigurationGraph($graph->nodes());

        foreach ($graph->nodes() as $node) {
            $value = $node->value();

            if (!\is_string($value)) {
                continue;
            }

            $interpolated = $this->interpolate($value, $graph);

            if ($interpolated === $value) {
                continue;
            }

            $next->put($this->rewrite($node, $interpolated));
        }

        return $next;
    }

    private function interpolate(string $value, ConfigurationGraphInterface $graph): string
    {
        return (string) \preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($graph): string {
                $key = $match[1];

                if (!$graph->has($key)) {
                    return $match[0];
                }

                $resolved = $graph->node($key)->value();

                return \is_scalar($resolved) || $resolved === null ? (string) $resolved : $match[0];
            },
            $value,
        );
    }

    private function rewrite(ConfigurationNodeInterface $node, string $value): ConfigurationNode
    {
        return new ConfigurationNode(
            key: $node->key(),
            value: $value,
            source: $node->source(),
            priority: $node->priority(),
            metadata: $node->metadata(),
        );
    }
}
