# ADR-0011: Execution Occurrence Naming Convention

## Status

Accepted.

## Context

Core records execution artifacts (context, occurrences, observations,
measurements, snapshots) through `Contract\ExecutionRecorderInterface` so a
future adapter (OpenTelemetry, CloudEvents, RunIt) can transport them. If
occurrence names drift with refactoring, downstream consumers (dashboards,
alerts, event routers) break silently.

## Decision

Every occurrence/artifact name is:

- **lowercase**,
- **stable**, and
- **versioned** with a `.vN` suffix.

Names follow the pattern `disenchant.it.<concept>.<event>.v1`, for example:

- `disenchant.it.magic_value.detected.v1`
- `disenchant.it.scan.v1`
- `disenchant.it.decide.v1`
- `disenchant.it.rewrite.v1`
- `disenchant.it.semconv.collect.v1`
- `disenchant.it.semconv.alias.v1`
- `disenchant.it.snapshot.v1`
- `disenchant.it.context.v1`
- `disenchant.it.observation.v1`
- `disenchant.it.measurement.v1`

The name is independent of the PHP class name. A meaning change bumps the
version (`v2`) rather than reusing the old name with new semantics; a name
collision with a *differing* meaning is a hard error at the consumer boundary.

## Consequences

- Adapters map artifacts to external event names without re-deriving structure.
- Renaming a PHP class never renames a wire event.
- Versioning gives consumers a safe migration path.

## References

- `docs/REFACTORING_PLAN.md` §17
- `src/ValueObject/Execution/*`
- `src/Core/Disenchanter.php` (recording calls)
