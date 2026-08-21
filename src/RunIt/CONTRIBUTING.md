# Contributing to RunIt

RunIt is a vendor-neutral execution model. Contributions must preserve its domain
vocabulary and its hexagonal dependency direction.

## Scope and status

RunIt targets PHP `>= 8.4` and has not yet published a stable `1.0.0`. Until then,
breaking changes may land on `main`; see [`docs/UPGRADING.md`](docs/UPGRADING.md)
and [`docs/compatibility.md`](docs/compatibility.md). Public API, lifecycle,
serialization, security-default, and architectural-boundary changes require an ADR
in `docs/adr/`.

## Reporting issues

- For suspected **security** vulnerabilities, follow [`docs/SECURITY.md`](docs/SECURITY.md)
  and do not open a public issue.
- For bugs and features, open an issue with a minimal reproduction and the affected
  version or commit.

## Development checks

Install dependencies with Composer, then run the checks relevant to a change:

```bash
composer test
composer analyse
composer architecture
composer cs:check
```

`composer cs:check` reports coding-style violations without modifying files;
`composer cs:fix` applies the project's PHP-CS-Fixer ruleset automatically. Run
`composer cs:fix` before committing to keep the tree clean. Conformance changes
should also pass `composer test:conformance`; API-surface changes should pass
`composer api:check`.

## Before submitting a change

1. Update focused tests and documentation alongside behavior changes.
2. Do not add vendor-specific types to the domain or public contracts.
3. Add or update an ADR for any compatibility-sensitive or architectural change.
4. Keep patches small and reviewable; preserve unrelated formatting and behavior.
5. Run the narrowest relevant checks, then the broader quality gates.

See `AGENTS.md` for the complete engineering rules, and `README.md` for usage.
