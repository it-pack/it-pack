# Contributing to AsyncIt

AsyncIt is the portable, capability-oriented scheduling and progression engine for the ItPack
ecosystem. It owns *how* work progresses within a RunIt execution — task queues, Fiber
suspension/resumption, cooperative cancellation, timers, and IO awaiting — strictly decoupled
from operating-system multiplexers, polling engines, and vendor runtimes.

Contributions must preserve AsyncIt's domain vocabulary (`Task`, `Scheduler`, `Poller`,
`Cancellation`, `Awaiting`, not "span/coroutine/job/event-loop") and its hexagonal dependency
direction (Domain → Contracts → Application → SDK → Adapters; adapters never leak into domain).

## Scope and status

AsyncIt targets PHP `>= 8.5` and has not yet published a stable `1.0.0`. Until then, breaking
changes may land on `main`; public API, lifecycle, serialization, security-default, and
architectural-boundary changes require an Architecture Decision Record (ADR) in
`docs/background/`.

See `AGENTS.md` for the complete engineering rules, `README.md` for usage, and
`docs/REFACTORING_PLAN.md` for the active refactoring plan.

## Reporting issues

- For suspected **security** vulnerabilities (context bleed, handle leaks, unbounded queues,
  metric-name injection), do not open a public issue. Contact the maintainer through the
  repository's security contact.
- For bugs and features, open an issue with a minimal reproduction, the affected version or
  commit, and the PHP/runtime environment.

## Development setup

```bash
composer install
```

## Coding standards

AsyncIt follows PSR-12-adjacent conventions enforced by PHP-CS-Fixer, Rector, PHPStan (max
level), and phparkitect. Every PHP file begins with:

```php
<?php

declare(strict_types=1);
```

Key rules (see `AGENTS.md` §PHP Standards):

- Concrete classes are `final`; eligible classes are `readonly`; every concrete class implements
  an appropriate interface; interfaces depend only on interfaces/enums.
- Declaration order: traits → constants → properties/hooks → constructors → factory methods →
  non-static → static; public before protected/private within each section.
- No `else`/`elseif`, no Yoda-style conditions, no magic literals, one type per file,
  ≤120 chars/line.
- Every public contract/enum/value object carries a PHPDoc `@see` to the matching vocabulary
  resource in `vocab/async-it.ttl` or `vocab/async-it-taxonomy.ttl` (or RunIt's
  `run-it.ttl` for consumed terms).
- Exceptions implement the relevant AsyncIt exception marker interface.
- Durations/timers use monotonic clocks (`hrtime()`), never wall-clock time.

Apply the project's PHP-CS-Fixer ruleset before committing:

```bash
composer cs:fix
composer cs:check      # reports violations without modifying files
```

Rector dry-run must be clean:

```bash
composer refactor:check
```

## Testing requirements

Every behavior change requires tests at the narrowest appropriate level. AsyncIt uses **Codeception**
(`Unit` suite subclasses `PHPUnit\Framework\TestCase`); PHPUnit is not a direct first-class dependency.

```bash
composer test:unit          # Unit suite
composer test               # Unit + Integration + Functional + Conformance + Concurrency
```

Conformance is proven by behavior, not interface shape. Adapters must pass the
`AsyncRuntimeVerifier` suite (Assertion A context isolation, Assertion B temporal precision,
Assertion C deterministic cleanup). Security-sensitive behavior requires negative tests
(context-bleed, handle-leak).

```bash
composer test:conformance   # AsyncRuntimeVerifier for each adapter
```

## Quality gate

Run the full quality chain before pushing:

```bash
composer quality
```

This runs, in order:

1. `composer validate --strict`
2. `composer assumptions` (PHP arch)
3. `composer refactor:check` (Rector dry-run)
4. `composer cs:check` (PHP-CS-Fixer dry-run)
5. `composer analyse` (PHPStan, max level)
6. `composer architecture` (phparkitect)
7. `composer lint:length` and `composer lint:types`
8. `composer test` (all Codeception suites)
9. `composer benchmark` (phpbench hot-path benchmarks)
10. `composer security:audit` (composer audit)

Individual lanes can be run separately (`composer analyse`, `composer architecture`,
`composer cs:check`, `composer test`, `composer benchmark`). `composer cs:check` / `composer
analyse` / `composer architecture` must report zero violations; `composer test` must pass all
suites.

## Architecture decision process (ADR)

Create or update an ADR under `docs/background/` when a change affects:

- Aggregate boundaries (`Task`, `Scheduler`)
- Dependency direction
- Public extension points
- Lifecycle semantics
- Failure guarantees
- Context propagation
- Package splitting
- Security defaults
- Serialization compatibility

ADR naming: `docs/background/adr-<NNN>-<short-slug>.md` (e.g. `adr-AI-001-...`). Each ADR
covers **Context / Decision / Consequences** and references the affected public contracts. Do not
add phparkitect skips to hide a violation; correct the dependency direction and update the ADR
first.

## Before submitting a change

1. Update focused tests and documentation alongside behavior changes.
2. Run the narrowest relevant checks, then `composer quality`.
3. Keep patches small and reviewable; preserve unrelated formatting and behavior.
4. Add or update an ADR for any compatibility-sensitive or architectural change.
5. Do not add vendor-specific types to domain or public contracts.
6. Do not commit generated artifacts (vendor, caches, coverage output) unless the repository
   requires them.

## Release notes

User-visible changes are recorded in `CHANGELOG.md` following the Keep a Changelog format and
Semantic Versioning (0.x: breaking changes may occur between minor releases).
