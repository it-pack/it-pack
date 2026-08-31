# AGENTS.md — `it-pack/hash-it`

Guidance for automated agents (Kilo, Claude, etc.) working in this package.

## Hard quality bar

This package is held to a **stricter standard than its own php-cs-fixer config** (see
`docs/REVIEW.md` → "Assumptions"). New code must satisfy **all** of the following, or
CI `strict-gates` turns red:

| Rule | Enforcement |
|---|---|
| No `else` / `elseif` (F-3) | php-cs-fixer `no_superfluous_else` / `no_superfluous_elseif` + `composer cs:strict` |
| Every referenced type imported via `use` (F-5) | `composer cs:strict` (no inline `\ItPack\HashIt\…`) |
| ≤ 120 columns per line (F-6) | `composer cs:strict` |
| One type per file (PSR-4) | project convention |
| No abstract classes | use traits / composition |
| No magic literals | prefer `const` / `enum` in the owning interface |
| `new` only in factories / composition root (`NativeHashIt`) | deptrac + `composer cs:strict` review |
| Declaration order: trait → const → props → ctor → factory → non-static → static, public before non-public | project convention |
| Tests: ≥ 95% statement & method coverage, no cross-test leakage | `composer coverage` |
| Mutation testing | `composer mutation` (infection 0.33; gate `minMsi: 73` — see `docs/REFACTORING_PLAN.md` M2 for recorded 73.74 % baseline and the 80 % target / M2.4-strengthen follow-up) |

## Quality commands

```bash
composer test:unit    # Codeception unit suite
composer coverage     # unit suite + coverage (target ≥95%)
composer analyse      # PHPStan
composer architecture # deptrac layer rules
composer cs:check     # PHP-CS-Fixer (dry-run)
composer cs:fix       # PHP-CS-Fixer (apply)
composer cs:strict    # review-bar gates F-3 / F-5 / F-6
composer mutation     # infection (gate minMsi=73; recorded 73.74% baseline)
```

## Refactoring

Follow `docs/REFACTORING_PLAN.md` milestone-by-milestone. Do **not** skip the
verification gate at the end of a milestone, and keep each concern in its own commit.
Value objects (`Hash`, `HashManifest`, `VerificationReport`, `HashObservation`,
`HashMismatch`, `HashProgress`) are exempt from the "every class needs an interface"
rule.
