# Security Policy

RunIt models execution itself, so its artifacts can carry sensitive runtime data.
This document summarizes how the library protects that data and how to report
problems. It complements the engineering rules in `AGENTS.md` and the detailed
analysis in `docs/security/threat-model.md` and `docs/security/supply-chain.md`.

## Reporting a vulnerability

Do **not** open a public issue for a suspected security vulnerability. Report it
privately to the package maintainers through the ItPack security contact, including:

- a description of the affected behavior and the version or commit;
- a minimal reproduction (code or sequence of steps);
- the expected vs. observed behavior;
- any known impact (data exposure, privilege boundary crossing, denial of service).

Maintainers acknowledge reports promptly, coordinate a fix, and credit reporters
unless anonymity is requested. Security fixes take priority over feature work.

## Supported versions

| Version line | Supported |
|---|---|
| `1.0.x` (after stable release) | Yes |
| `0.x` / pre-release | Best effort; upgrade to the latest commit |

Before `1.0.0`, RunIt may introduce breaking changes; treat the `main` branch as
the supported line. After `1.0.0`, RunIt follows semantic versioning and the
upgrade notes in `UPGRADING.md`.

## Security model

RunIt treats context, observations, occurrences, and snapshots as potential
data-exfiltration paths and applies defense-in-depth:

- **Classify.** Every context entry carries a classification (`public`,
  `internal`, `confidential`, `restricted`, `secret`). Classification decides
  default export, propagation, and serialization behavior. See
  [`docs/concepts/context.md`](docs/concepts/context.md).
- **Redact before publication.** Secret values are never exported and never
  serialized; restricted values are masked; internal and confidential values are
  dropped on export. Redaction is deny-by-default (ADR-012).
- **Bound everything.** Keys, values, attribute maps, occurrences, and snapshots
  are size- and cardinality-bounded through `SecurityLimits`.
- **Never serialize arbitrary objects.** Normalization produces only recursively
  primitive arrays; throwables are normalized to safe `ThrowableDescription`
  projections, never raw stack traces.

## Threat model and supply chain

- [`docs/security/threat-model.md`](docs/security/threat-model.md) enumerates the
  trust boundaries, data-flow assumptions, and recommended controls.
- [`docs/security/supply-chain.md`](docs/security/supply-chain.md) covers
  dependency hygiene, optional dependencies, and release integrity.

## Default limits

The shipped `SecurityLimits` bound context entries, value lengths, array element
counts, attribute depth, and payload sizes. These defaults are explicit and can be
overridden deliberately; see `docs/QA.md` and the `Security` section of
[`README.md`](README.md).

## External adapters

OpenTelemetry, CloudEvents, PSR, Swoole, and OpenSwoole are optional adapters. Core
code never configures global providers or selects an exporter, and it must not
introduce a mandatory adapter dependency (ADR-006, ADR-010). Run
`composer audit` and the project quality checks before release.
