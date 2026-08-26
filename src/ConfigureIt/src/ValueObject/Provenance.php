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
        public SourceKind $sourceKind,
        public array   $processorTrail = [],
        public bool    $overridden = false,
        public ?string $overriddenSource = null,
        public ?string $validatedBy = null,
        public ?string $layerId = null,
        public float   $at = 0.0,
        public bool    $sensitive = false,
    ) {
    }

    /**
     * Reconstruct an immutable provenance record from {@see self::toArray()}.
     *
     * Used by the compiled-artifact cache to rebuild an equal snapshot from a
     * serialized artifact (M3 — Epic E7.1).
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $at = $data['at'] ?? 0.0;
        $at = is_numeric($at) ? (float) $at : 0.0;

        return new self(
            resourceUri: is_string($data['resourceUri'] ?? null) ? $data['resourceUri'] : '',
            format: isset($data['format']) && is_string($data['format'])
                ? Format::fromString($data['format'])
                : Format::UNKNOWN,
            adapter: is_string($data['adapter'] ?? null) ? $data['adapter'] : '',
            priority: is_numeric($data['priority'] ?? null) ? (int) $data['priority'] : 0,
            sourceKind: is_string($data['sourceKind'] ?? null)
                ? SourceKind::fromString($data['sourceKind'])
                : SourceKind::Other,
            processorTrail: self::stringList($data['processorTrail'] ?? null),
            overridden: ($data['overridden'] ?? false) === true,
            overriddenSource: isset($data['overriddenSource']) && is_string($data['overriddenSource'])
                ? $data['overriddenSource']
                : null,
            validatedBy: isset($data['validatedBy']) && is_string($data['validatedBy'])
                ? $data['validatedBy']
                : null,
            layerId: isset($data['layerId']) && is_string($data['layerId']) ? $data['layerId'] : null,
            at: $at,
            sensitive: ($data['sensitive'] ?? false) === true,
        );
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
        return $this->sourceKind->value;
    }

    public function layerId(): ?string
    {
        return $this->layerId;
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

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function withProcessor(string $stageName): self
    {
        if (in_array($stageName, $this->processorTrail, true)) {
            return $this;
        }

        return $this->copy(['processorTrail' => [...$this->processorTrail, $stageName]]);
    }

    public function asOverridden(?string $shadowedSource = null): self
    {
        if ($this->overridden && $this->overriddenSource === $shadowedSource) {
            return $this;
        }

        return $this->copy(['overridden' => true, 'overriddenSource' => $shadowedSource]);
    }

    public function validated(string $stageName): self
    {
        if ($this->validatedBy === $stageName) {
            return $this;
        }

        return $this->copy(['validatedBy' => $stageName]);
    }

    public function asSensitive(): self
    {
        if ($this->sensitive) {
            return $this;
        }

        return $this->copy(['sensitive' => true]);
    }

    public function toArray(): array
    {
        return [
            'resourceUri'     => $this->resourceUri,
            'format'          => $this->format->name,
            'adapter'         => $this->adapter,
            'priority'        => $this->priority,
            'sourceKind'      => $this->sourceKind->value,
            'processorTrail'  => $this->processorTrail,
            'overridden'      => $this->overridden,
            'overriddenSource' => $this->overriddenSource,
            'validatedBy'     => $this->validatedBy,
            'layerId'         => $this->layerId,
            'at'              => $this->at,
            'sensitive'       => $this->sensitive,
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * Reconstructs an equal provenance with the given fields overridden.
     *
     * Centralizes the twelve constructor arguments so the mutators stay
     * one-liners and future discriminator changes (enums, new fields) touch a
     * single place. Each override key maps to a constructor parameter; any key
     * omitted falls back to the current instance value.
     *
     * @param array{
     *   resourceUri?: string,
     *   format?: Format,
     *   adapter?: string,
     *   priority?: int,
     *   sourceKind?: SourceKind,
     *   processorTrail?: list<string>,
     *   overridden?: bool,
     *   overriddenSource?: ?string,
     *   validatedBy?: ?string,
     *   layerId?: ?string,
     *   at?: float,
     *   sensitive?: bool
     * } $overrides
     */
    private function copy(array $overrides): self
    {
        return new self(
            resourceUri:     $overrides['resourceUri']     ?? $this->resourceUri,
            format:          $overrides['format']          ?? $this->format,
            adapter:         $overrides['adapter']         ?? $this->adapter,
            priority:        $overrides['priority']        ?? $this->priority,
            sourceKind:      $overrides['sourceKind']      ?? $this->sourceKind,
            processorTrail:  $overrides['processorTrail']  ?? $this->processorTrail,
            overridden:      $overrides['overridden']      ?? $this->overridden,
            overriddenSource: $overrides['overriddenSource'] ?? $this->overriddenSource,
            validatedBy:     $overrides['validatedBy']     ?? $this->validatedBy,
            layerId:         $overrides['layerId']         ?? $this->layerId,
            at:              $overrides['at']              ?? $this->at,
            sensitive:       $overrides['sensitive']       ?? $this->sensitive,
        );
    }
}
