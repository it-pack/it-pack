<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * Applies default values for keys absent from the graph.
 *
 * A default only fills a missing key; it never overrides an explicitly resolved
 * value, regardless of priority. Implements the "Apply Defaults" pipeline stage.
 *
 * @see docs/architecture/PIPELINE.md "Defaults"
 */
final readonly class DefaultsProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface
{
    /**
     * @param array<string, mixed> $defaults flat dotted-key defaults.
     */
    public function __construct(
        private array $defaults,
    ) {
    }

    public function stageName(): string
    {
        return 'DefaultsProcessor';
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
                provenance: new Provenance(
                    resourceUri: 'defaults',
                    format: Format::PHP,
                    adapter: 'DefaultsProcessor',
                    priority: 0,
                    sourceKind: 'default',
                    at: microtime(true),
                ),
            ));
        }

        return $next;
    }
}
