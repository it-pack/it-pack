# Contributing to RunIt

RunIt is a vendor-neutral execution model. Contributions must preserve its
domain vocabulary and its hexagonal dependency direction.

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
`composer cs:fix` before committing to keep the tree clean.

Before submitting a change, update focused tests and documentation. Do not add
vendor-specific types to the domain or public contracts. Public API, lifecycle,
serialization, security-default, and architectural-boundary changes require an
ADR in `docs/adr/`.

See `AGENTS.md` for the complete engineering rules.
