<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Normalises a parsed document into the flat list of graph nodes.
 *
 * Extracted from `ConfigurationPipeline` (ADR-0010) so normalisation is a
 * single-purpose, independently testable collaborator. Built on the `Key` value
 * object; the graph stays focused on priority-merge + snapshot production.
 *
 * @see docs/architecture/decisions/ADR-0010-Normalizer-Split.md
 */
interface DocumentNormalizerInterface
{
    /**
     * @return list<ConfigurationNodeInterface>
     */
    public function normalize(ConfigurationDocumentInterface $document, int $priority, string $origin): array;
}
