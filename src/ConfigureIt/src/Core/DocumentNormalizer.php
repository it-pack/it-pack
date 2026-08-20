<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * Default {@see DocumentNormalizerInterface}.
 *
 * Flattens a document's associative array into dotted-key {@see ConfigurationNode}s,
 * preserving source and priority, and attaches structured {@see Provenance} (the
 * loading adapter, format, source kind and priority) to every node. Moves the
 * `flatten()` concern out of {@see ConfigurationPipeline} (ADR-0010) and is the
 * single place where load-time provenance is minted (Roadmap Phase 6).
 *
 * @see docs/architecture/decisions/ADR-0010-Normalizer-Split.md
 * @see docs/architecture/GRAPH.md "Provenance"
 */
final readonly class DocumentNormalizer implements DocumentNormalizerInterface
{
    /**
     * @return list<ConfigurationNodeInterface>
     */
    public function normalize(ConfigurationDocumentInterface $document, int $priority, string $origin): array
    {
        return $this->flatten($document, $document->toArray(), '', $origin, $priority);
    }

    /**
     * @param array<mixed> $data
     * @param string $prefix
     * @param string $source
     * @param int $priority
     * @return list<ConfigurationNodeInterface>
     */
    private function flatten(
        ConfigurationDocumentInterface $document,
        array $data,
        string $prefix,
        string $source,
        int $priority,
    ): array {
        $key = $prefix === '' ? null : Key::fromString($prefix);
        $nodes = [];

        foreach ($data as $segment => $value) {
            $child = $key === null ? Key::fromString((string) $segment) : $key->child((string) $segment);

            if (is_array($value) && Key::isAssociative($value)) {
                $nodes = [...$nodes, ...$this->flatten($document, $value, $child->toString(), $source, $priority)];

                continue;
            }

            $nodes[] = new ConfigurationNode(
                key: $child->toString(),
                value: $value,
                source: $source,
                priority: $priority,
                metadata: [],
                provenance: new Provenance(
                    resourceUri: $source,
                    format: $document->format(),
                    adapter: $document->adapter() ?? 'unknown',
                    priority: $priority,
                    sourceKind: self::sourceKindOf($document->format(), $source),
                    at: microtime(true),
                ),
            );
        }

        return $nodes;
    }

    private static function sourceKindOf(Format $format, string $uri): string
    {
        $enterprise = [
            'configmap://' => 'configmap',
            'k8s-secret://' => 'k8s-secret',
            'docker-secret://' => 'docker-secret',
            'aws-ssm://' => 'aws-ssm',
            'azure-appconfig://' => 'azure-appconfig',
            'gcp-secretmanager://' => 'gcp-secretmanager',
        ];

        foreach ($enterprise as $scheme => $kind) {
            if (str_starts_with($uri, $scheme)) {
                return $kind;
            }
        }

        return match ($format) {
            Format::ENV, Format::DOTENV => 'env',
            Format::PHP => 'programmatic',
            default => 'file',
        };
    }
}
