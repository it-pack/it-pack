<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * A parsed, format-independent representation of configuration.
 *
 * Produced by loaders, consumed by the pipeline. Carries the origin uri and the
 * source format so downstream stages can attribute values correctly.
 *
 * @see docs/architecture/MODEL.md "Configuration Document"
 */
final readonly class ConfigurationDocument implements ConfigurationDocumentInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
        private Format $format,
        private ?string $origin = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function origin(): ?string
    {
        return $this->origin;
    }

    public function format(): Format
    {
        return $this->format;
    }
}
