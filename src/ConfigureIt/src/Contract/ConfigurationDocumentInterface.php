<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * A parsed, format-independent representation of configuration.
 *
 * Documents are produced by loaders and consumed by processors/pipelines. They
 * are independent of file format, storage and transport.
 *
 * @see docs/architecture/MODEL.md "Configuration Document"
 */
interface ConfigurationDocumentInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @return string|null the origin identifier (uri) of the source, if known.
     */
    public function origin(): ?string;

    /**
     * The format this document was parsed from.
     */
    public function format(): \ItPack\ConfigureIt\ValueObject\Format;

    /**
     * The adapter (loader class) that produced this document, when known.
     *
     * Recorded so downstream provenance can attribute each node to the concrete
     * loader that parsed it (Roadmap Phase 6).
     */
    public function adapter(): ?string;
}
