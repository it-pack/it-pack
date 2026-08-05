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

Before submitting a change, update focused tests and documentation. Do not add
vendor-specific types to the domain or public contracts. Public API, lifecycle,
serialization, security-default, and architectural-boundary changes require an
ADR in `docs/adr/`.

See `AGENTS.md` for the complete engineering rules.
