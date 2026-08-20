<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;

/**
 * Immutable provenance of a single resolved configuration node.
 *
 * Provenance is built at load time (format + adapter + source) and enriched as
 * the resolution pipeline runs: processors append themselves to the
 * {@see processorTrail()}, overrides are flagged via {@see asOverridden()}, and
 * validation records the validating stage via {@see validated()}.
 *
 * @see docs/architecture/GRAPH.md "Provenance"
 */
final readonly class Provenance implements ProvenanceInterface
{
    /**
     * @param list<string> $processorTrail
     */
    public function __construct(
        public string  $resourceUri,
        public Format  $format,
        public string  $adapter,
        public int     $priority,
        public string  $sourceKind,
        public array   $processorTrail = [],
        public bool    $overridden = false,
        public ?string $overriddenSource = null,
        public ?string $validatedBy = null,
        public float   $at = 0.0,
    ) {
    }

    public function resourceUri(): string
    {
        return $this->resourceUri;
    }

    public function format(): Format
    {
        return $this->format;
    }

    public function adapter(): string
    {
        return $this->adapter;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function sourceKind(): string
    {
        return $this->sourceKind;
    }

    public function processorTrail(): array
    {
        return $this->processorTrail;
    }

    public function overridden(): bool
    {
        return $this->overridden;
    }

    public function overriddenSource(): ?string
    {
        return $this->overriddenSource;
    }

    public function validatedBy(): ?string
    {
        return $this->validatedBy;
    }

    public function at(): float
    {
        return $this->at;
    }

    public function withProcessor(string $stageName): self
    {
        if (in_array($stageName, $this->processorTrail, true)) {
            return $this;
        }

        return new self(
            resourceUri: $this->resourceUri,
            format: $this->format,
            adapter: $this->adapter,
            priority: $this->priority,
            sourceKind: $this->sourceKind,
            processorTrail: [...$this->processorTrail, $stageName],
            overridden: $this->overridden,
            overriddenSource: $this->overriddenSource,
            validatedBy: $this->validatedBy,
            at: $this->at,
        );
    }

    public function asOverridden(?string $shadowedSource = null): self
    {
        if ($this->overridden && $this->overriddenSource === $shadowedSource) {
            return $this;
        }

        return new self(
            resourceUri: $this->resourceUri,
            format: $this->format,
            adapter: $this->adapter,
            priority: $this->priority,
            sourceKind: $this->sourceKind,
            processorTrail: $this->processorTrail,
            overridden: true,
            overriddenSource: $shadowedSource,
            validatedBy: $this->validatedBy,
            at: $this->at,
        );
    }

    public function validated(string $stageName): self
    {
        if ($this->validatedBy === $stageName) {
            return $this;
        }

        return new self(
            resourceUri: $this->resourceUri,
            format: $this->format,
            adapter: $this->adapter,
            priority: $this->priority,
            sourceKind: $this->sourceKind,
            processorTrail: $this->processorTrail,
            overridden: $this->overridden,
            overriddenSource: $this->overriddenSource,
            validatedBy: $stageName,
            at: $this->at,
        );
    }

    public function toArray(): array
    {
        return [
            'resourceUri'     => $this->resourceUri,
            'format'          => $this->format->name,
            'adapter'         => $this->adapter,
            'priority'        => $this->priority,
            'sourceKind'      => $this->sourceKind,
            'processorTrail'  => $this->processorTrail,
            'overridden'      => $this->overridden,
            'overriddenSource' => $this->overriddenSource,
            'validatedBy'     => $this->validatedBy,
            'at'              => $this->at,
        ];
    }
}
