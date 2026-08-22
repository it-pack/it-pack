<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\DocumentNormalizerInterface;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

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
    public function normalize(ConfigurationDocumentInterface $document, int $priority, string $origin, ?string $layerId = null): array
    {
        return $this->flatten($document, $document->toArray(), '', $origin, $priority, $layerId);
    }

    /**
     * @param array<mixed> $data
     * @param string $prefix
     * @param string $source
     * @param int $priority
     * @param string|null $layerId
     * @return list<ConfigurationNodeInterface>
     */
    private function flatten(
        ConfigurationDocumentInterface $document,
        array $data,
        string $prefix,
        string $source,
        int $priority,
        ?string $layerId = null,
    ): array {
        $key = $prefix === '' ? null : Key::fromString($prefix);
        $nodes = [];

        foreach ($data as $segment => $value) {
            $child = $key === null ? Key::fromString((string) $segment) : $key->child((string) $segment);

            if (is_array($value) && Key::isAssociative($value)) {
                $nodes = [...$nodes, ...$this->flatten($document, $value, $child->toString(), $source, $priority, $layerId)];

                continue;
            }

            $sensitive = self::isSecretScheme($source);

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
                    layerId: $layerId,
                    at: microtime(true),
                    sensitive: $sensitive,
                ),
            );
        }

        return $nodes;
    }

    /**
     * Schemes that identify secret-bearing sources. Values resolved from these
     * are flagged {@see ProvenanceInterface::isSensitive()} so the central
     * {@see \ItPack\ConfigureIt\Core\RedactingRenderer} can mask them everywhere
     * (M4 — Epic E5.1). The specific {@see self::sourceKindOf()} is preserved for
     * provenance richness.
     */
    private const SECRET_SCHEMES = [
        'k8s-secret://',
        'docker-secret://',
        'aws-ssm://',
        'gcp-secretmanager://',
        'vault://',
    ];

    private static function isSecretScheme(string $uri): bool
    {
        foreach (self::SECRET_SCHEMES as $scheme) {
            if (str_starts_with($uri, $scheme)) {
                return true;
            }
        }

        return false;
    }

    private static function sourceKindOf(Format $format, string $uri): SourceKind
    {
        $enterprise = [
            'configmap://' => SourceKind::ConfigMap,
            'k8s-secret://' => SourceKind::K8sSecret,
            'docker-secret://' => SourceKind::DockerSecret,
            'aws-ssm://' => SourceKind::AwsSsm,
            'azure-appconfig://' => SourceKind::AzureAppConfig,
            'gcp-secretmanager://' => SourceKind::GcpSecretManager,
            'vault://' => SourceKind::Vault,
            'http://' => SourceKind::Http,
            'https://' => SourceKind::Http,
            'consul://' => SourceKind::Consul,
            'etcd://' => SourceKind::Etcd,
        ];

        foreach ($enterprise as $scheme => $kind) {
            if (str_starts_with($uri, $scheme)) {
                return $kind;
            }
        }

        return match ($format) {
            Format::ENV, Format::DOTENV => SourceKind::Env,
            Format::PHP => SourceKind::Programmatic,
            default => SourceKind::File,
        };
    }
}
