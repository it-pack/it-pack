<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;

/**
 * Applies default values for keys absent from the graph.
 *
 * A default only fills a missing key; it never overrides an explicitly resolved
 * value, regardless of priority. Implements the "Apply Defaults" pipeline stage.
 *
 * @see docs/architecture/PIPELINE.md "Defaults"
 */
final class DefaultsProcessor implements ConfigurationProcessorInterface
{
    /**
     * @param array<string, mixed> $defaults flat dotted-key defaults.
     */
    public function __construct(
        private readonly array $defaults,
    ) {
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $next = new ConfigurationGraph($graph->nodes());

        foreach ($this->defaults as $key => $value) {
            if ($next->has($key)) {
                continue;
            }

            $next->put(new ConfigurationNode(
                key: $key,
                value: $value,
                source: 'defaults',
                priority: 0,
                metadata: ['origin' => 'defaults'],
            ));
        }

        return $next;
    }
}
