# ADR-0011 — Node-rewrite decomposition (no abstract classes)

- **Status:** Accepted (2026-08-26, Milestone 5 / NV7)
- **Supersedes:** the `Core\AbstractNodeRewritingProcessor` template-method base
  introduced in Milestone 4 (R18).

## Context

`CastingProcessor` and `ValidationProcessor` shared an identical "rewrite every
node of the graph" loop (clone graph → loop nodes → `put`). Milestone 4 had
extracted that into `AbstractNodeRewritingProcessor` (a `template method`:
subclasses implement `rewrite()`, the base owns `process()` + an optional
`before()` hook).

REVIEW NV7 flagged that ConfigureIt's stated bar is **no abstract classes;
prefer trait/composition**. The single remaining `abstract class` was a
deliberate, after-the-fact introduction that the policy forbids. More
importantly, an abstract base couples the two processors to inheritance and
blocks the project's composition-first direction.

## Decision

Replace the abstract base with a **stateless `ConfigurationGraphRewriter`
collaborator** in `Core`:

```php
final readonly class ConfigurationGraphRewriter
{
    public function rewrite(
        ConfigurationGraphInterface $graph,
        Closure $rewrite,
        ?Closure $before = null,
    ): ConfigurationGraphInterface;
}
```

- The clone-graph → loop-nodes → put sequence and the optional whole-graph
  `before()` hook live here verbatim.
- `CastingProcessor` and `ValidationProcessor` now `implement`
  `ConfigurationProcessorInterface` + `ProvenanceAwareProcessorInterface` and
  delegate `process()` to the injected rewriter (closure over their per-node
  logic). They receive the rewriter via the new `graphRewriter()` seam on
  `ConfigurationWiringInterface` — keeping the "no service builds another
  service" policy (NV10) intact.
- A tokenizer guard (`NoAbstractClassesTest`) fails the build if any `T_ABSTRACT`
  token reappears under `src/`.

## Consequences

- **Positive:** zero abstract classes; processors compose the rewriter instead
  of inheriting it; the rewriter is independently unit-testable; DI policy
  preserved.
- **Neutral:** two extra constructor dependencies on the processors (supplied
  by the composition root). Callers constructing these processors directly must
  now pass a `ConfigurationGraphRewriter` (e.g. `new CastingProcessor($casts,
  new ConfigurationGraphRewriter())`).
- **Negative:** none identified.
