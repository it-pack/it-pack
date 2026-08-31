# Architecture diagrams

Generated with [Archify](https://github.com/tt-a1i/archify) from a typed JSON
specification. The HTML artifact is a self-contained, interactive document
(themed, pan/zoom, search, focus, relationship tracing, multiple export
formats); open it directly in a browser, no server required.

## Files

| File | What it is |
|---|---|
| `derive-it-architecture.json` | The typed spec (architecture diagram, showcase quality). Edit this to evolve the diagram; the HTML is regenerated from it. |
| `derive-it-architecture.html` | The rendered, interactive diagram. Regenerated from the spec via the Archify `deliver` command. |

## Regenerate

```bash
node /home/quill/.agents/skills/archify/bin/archify.mjs validate architecture \
    docs/diagram/derive-it-architecture.json --quality showcase --json

node /home/quill/.agents/skills/archify/bin/archify.mjs deliver architecture \
    docs/diagram/derive-it-architecture.json \
    docs/diagram/derive-it-architecture.html --quality showcase --json
```

Both must exit 0 (the deliver receipt is the acceptance gate).

## What the diagram shows

A high-level runtime map of `it-pack/derive-it`:

- **11 core runtime components** — CLI, `DeriveIt` facade, `DeriveItFactory`
  composition root, the four derivers (Interface / PerTrait / Family /
  Adapter), Recursive Resolvers, Reflection + Cache, the signature &
  substitution engine, `SourceRenderer`, `LeakageAuditor`,
  `DeriveConfig`, and the telemetry emitter.
- **One primary derivation path** — `CLI → DeriveIt → DeriveItFactory →
  Derivers → Engine → SourceRenderer`, with the structural side branches
  (resolvers, reflection, auditor, config, telemetry) shown as edges off the
  nearest main-path node.
- **External dependencies** — `nette/php-generator`, PHP Reflection, and the
  optional `psr/cache`, plus the untrusted `adaptee` input.
- **Ownership & trust boundaries** — a `DeriveIt runtime` region marks the
  code you own; a `Leakage trust boundary` security group around the
  generation core states the invariant that the derived port must not
  reference any adaptee parent / trait / interface.
- **Supporting detail in cards** — entry/composition, derivers & graph walk,
  reflection & cache, signature & substitution, and self-containment & config
  each live in a single card so the topology stays readable.

## Composition rules

The spec follows the `architecture` schema and targets the **showcase**
quality profile. All nine composition checks (schema, semantic, layout,
containment, label-route clearance, proper crossings, endpoint-side
direction, micro-segments, and desktop readability) must report `pass`; a
non-zero exit is never treated as success. See the SKILL contract for the
full authoring invariants.
