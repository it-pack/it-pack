<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Node;

use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * Factory for {@see ConfigurationNode} instances with sensible test defaults.
 *
 * Produces nodes carrying provenance so the {@see \ItPack\ConfigureItTesting\Assertions\ConfigurationAssertions}
 * trait can assert on source kind, override status, sensitivity, format and
 * adapter without hand-building {@see Provenance} objects every time.
 */
final class FakeNode
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $key,
        mixed $value,
        string $source = 'programmatic',
        int $priority = 0,
        array $metadata = [],
    ): ConfigurationNode {
        return new ConfigurationNode($key, $value, $source, $priority, $metadata);
    }

    public static function withProvenance(
        string $key,
        mixed $value,
        ProvenanceInterface $provenance,
    ): ConfigurationNode {
        return new ConfigurationNode(
            $key,
            $value,
            $provenance->resourceUri(),
            $provenance->priority(),
            [],
            $provenance,
        );
    }

    public static function sensitive(
        string $key,
        mixed $value,
        string $source = 'vault',
        int $priority = 0,
        string $sourceKind = 'vault',
    ): ConfigurationNode {
        $provenance = (new Provenance(
            resourceUri: $source,
            format: Format::PHP,
            adapter: 'fake',
            priority: $priority,
            sourceKind: $sourceKind,
        ))->asSensitive();

        return new ConfigurationNode($key, $value, $source, $priority, [], $provenance);
    }

    public static function override(
        string $key,
        mixed $value,
        string $source,
        int $priority,
        string $overriddenSource,
        string $sourceKind = 'programmatic',
    ): ConfigurationNode {
        $provenance = new Provenance(
            resourceUri: $source,
            format: Format::PHP,
            adapter: 'fake',
            priority: $priority,
            sourceKind: $sourceKind,
            overridden: true,
            overriddenSource: $overriddenSource,
        );

        return new ConfigurationNode($key, $value, $source, $priority, [], $provenance);
    }

    public static function from(
        string $key,
        mixed $value,
        string $source,
        int $priority,
        string $sourceKind = 'programmatic',
        string $adapter = 'fake',
        Format $format = Format::PHP,
    ): ConfigurationNode {
        $provenance = new Provenance(
            resourceUri: $source,
            format: $format,
            adapter: $adapter,
            priority: $priority,
            sourceKind: $sourceKind,
        );

        return new ConfigurationNode($key, $value, $source, $priority, [], $provenance);
    }
}
