# Quality Assurance & Static Analysis

This document describes the static-analysis gate enforced for `it-pack/run-it`
and the conventions behind it. It is the authoritative reference for the
Milestone M13 ("Conformance Tooling and QA Completion") static-analysis work.

## Gate overview

`composer analyse` runs PHPStan at the highest configured level (`max`) with:

1. **PHPStan core, level `max`** — the project's baseline.
2. **`phpstan/strict-rules` bundle** — auto-registered via
   `phpstan/extension-installer`. Enforces, among others: no loose (`==`)
   comparisons, no `empty()`, no short ternary, strict `in_array`, boolean-in-
   condition checks, no variable variables.
3. **RunIt-authored rules** — `tools/phpstan/runit-rules`, a small PHPStan
   extension implementing the project-specific rules below. No external
   rule packages were added; the rules live in the repository.

`composer quality` additionally runs `composer validate`, `php-cs-fixer`,
DepTrac, the test suites, and the security audit, so the static-analysis gate
is one component of a broader quality gate.

## RunIt-authored rules

| Rule | Enforces | AGENTS.md basis |
| --- | --- | --- |
| `forbidden-mixed` | No *implicit* (undeclared) parameter/return/property types. | "Avoid implicit `mixed`." |
| `forbidden-unsafe-types` | No `resource` type in signatures/properties. | `resource` cannot be serialized/observed safely; the other documented untyped types (`array`, `string`, `int`, `float`, `bool`, `object`, `callable`, `iterable`) remain permitted. |
| `no-var` | No `var` keyword; visibility must be explicit. | General PHP style. |
| `no-explicit-strict-types` | Every library file declares `declare(strict_types=1)`. | AGENTS.md: every file begins with `declare(strict_types=1)`. |
| `explicit-foreach` | No by-reference (`as &$v`) `foreach` binding. | Performance/determinism: avoid silent aliasing. |
| `forbid-reflection-on-hot-path` | No `Reflection*` usage in `Domain`, `Application\Runner`, `Application\Engine`. | AGENTS.md performance requirements (no reflection on hot paths). |

### Key decision: explicit `mixed` is permitted

AGENTS.md distinguishes between *implicit* `mixed` (a missing type hint) and
*explicit* `mixed`. It forbids the former and permits the latter as the escape
hatch for genuinely opaque or polymorphic boundaries — serializers, context/
occurrence/snapshot payloads, recorders, middleware, and polymorphic
validators. The `forbidden-mixed` rule therefore targets **implicit** types
only; explicit `mixed` remains a deliberate, reviewed choice at those
boundaries. This is also why these rules do not force breaking changes to public
contract signatures.

### `forbidden-unsafe-types` scope

Only `resource` is forbidden. The other untyped vocabulary types listed in
AGENTS.md (`array`, `string`, `int`, `float`, `bool`, `object`, `callable`,
`iterable`) are intentional and remain allowed.

### Import hygiene

`no-missing-imports` and `no-unused-imports` are enforced by `php-cs-fixer`
(`php-cs-fixer` rules `no_unused_imports` / `ordered_imports`), surfaced through
`composer cs:check`, not through PHPStan.

## Extending the rules

The extension lives at `tools/phpstan/runit-rules`:

- `extension.neon` — service registration and hot-path namespace list.
- `src/Rule/*` — one class per rule.
- `src/Support/*` — shared helpers (`AbstractSrcRule` restricts rules to the
  `src/` tree; `TypeNode` inspects declared type nodes).

Rules are restricted to the library source tree so the test suite and examples
are not subject to library-internal discipline. Add a new rule by implementing
`PHPStan\Rules\Rule` and registering it in `extension.neon`.
