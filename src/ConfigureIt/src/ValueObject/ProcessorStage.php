<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Named pipeline stages that rewrite a graph and append themselves to a node's
 * processor trail.
 *
 * Replaces the previously raw `'DefaultsProcessor'` stage-name string. Stage
 * names may also be arbitrary processor class names (for generic processors
 * that do not implement {@see \ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface}),
 * so {@see \ItPack\ConfigureIt\ValueObject\Provenance::withProcessor()} and
 * {@see \ItPack\ConfigureIt\ValueObject\Provenance::validated()} stay string-typed;
 * this enumeration documents the known, named stages.
 */
enum ProcessorStage: string
{
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Defaults;
    }
    case Defaults   = 'DefaultsProcessor';
    case Validation = 'ValidationProcessor';
    case Casting    = 'CastingProcessor';
    case Include    = 'IncludeProcessor';
    case Variable   = 'VariableProcessor';
}
