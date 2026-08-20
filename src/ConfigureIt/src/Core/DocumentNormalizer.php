<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;

/**
 * Default {@see DocumentNormalizerInterface}.
 *
 * Flattens a document's associative array into dotted-key {@see ConfigurationNode}s,
 * preserving source and priority. Moves the `flatten()` concern out of
 * `ConfigurationPipeline` (ADR-0010).
 *
 * @see docs/architecture/decisions/ADR-0010-Normalizer-Split.md
 */
final readonly class DocumentNormalizer implements DocumentNormalizerInterface
{
    /**
     * @return list<ConfigurationNodeInterface>
     */
    public function normalize(ConfigurationDocumentInterface $document, int $priority, string $origin): array
    {
        return $this->flatten($document->toArray(), '', $origin, $priority);
    }

    /**
     * @param array<mixed> $data
     * @param string $prefix
     * @param string $source
     * @param int $priority
     * @return list<ConfigurationNodeInterface>
     */
    private function flatten(array $data, string $prefix, string $source, int $priority): array
    {
        $key = $prefix === '' ? null : Key::fromString($prefix);
        $nodes = [];

        foreach ($data as $segment => $value) {
            $child = $key === null ? Key::fromString((string) $segment) : $key->child((string) $segment);

            if (is_array($value) && Key::isAssociative($value)) {
                $nodes = [...$nodes, ...$this->flatten($value, $child->toString(), $source, $priority)];

                continue;
            }

            $nodes[] = new ConfigurationNode(
                key: $child->toString(),
                value: $value,
                source: $source,
                priority: $priority,
                metadata: ['origin' => $source, 'format' => 'document'],
            );
        }

        return $nodes;
    }
}
