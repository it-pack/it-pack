<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Query;

use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;

/**
 * An immutable, fully-typed predicate over a single configuration node.
 *
 * Built by {@see QueryParser} from a textual expression. Matching is side-effect
 * free and reads only the structural provenance carried by every resolved node,
 * so it is safe to run against any graph (including one built from secrets).
 */
final readonly class QueryCriterion
{
    public function __construct(
        public QueryPredicate $predicate,
        public ?string $value,
    ) {
    }

    public function matches(ConfigurationNodeInterface $node): bool
    {
        $p = $node->provenance();

        return match ($this->predicate) {
            QueryPredicate::Source => null !== $this->value && $this->sourceMatches($p, $this->value),
            QueryPredicate::OverriddenBy => null !== $this->value
                && $p->overridden()
                && \basename((string) $p->overriddenSource()) === $this->value,
            QueryPredicate::Sensitive => $p->isSensitive(),
            QueryPredicate::Adapter => null !== $this->value
                && 0 === \strcasecmp($this->shortClass($p->adapter()), $this->value),
            QueryPredicate::Layer => null !== $this->value && ($p->layerId() ?? '') === $this->value,
            QueryPredicate::Format => null !== $this->value
                && 0 === \strcasecmp($p->format()->name, $this->value),
            QueryPredicate::Trail => null !== $this->value
                && \in_array($this->value, $p->processorTrail(), true),
        };
    }

    private function sourceMatches(ProvenanceInterface $provenance, string $value): bool
    {
        return 0 === \strcasecmp($provenance->sourceKind(), $value)
            || \basename($provenance->resourceUri()) === $value;
    }

    private function shortClass(string $fqcn): string
    {
        $pos = \strrpos($fqcn, '\\');

        return \strtolower(false === $pos ? $fqcn : \substr($fqcn, $pos + 1));
    }
}
