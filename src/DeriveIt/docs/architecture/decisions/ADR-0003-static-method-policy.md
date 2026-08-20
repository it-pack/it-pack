# ADR-0003: Static-method policy

- **Status:** Accepted
- **Date:** 2026-08-15

## Context

Ports (interfaces you own) generally should not force static method calls — a
static surface is hard to substitute, mock, or decorate. But PHP 8.4 permits static
interface methods, and some adaptees expose useful static factories.

## Decision

Drop static methods by default in every derivation mode. Make retention explicit
and opt-in via `DeriveConfig::includeStatic` (surfaced as the `$includeStatic`
shortcut on `DeriveIt::derive()` / `deriveInterface()`), which is safe on the
PHP ≥ 8.4 target.

## Consequences

- The default port is instance-oriented and easy to wrap with an adapter.
- Users who genuinely need static surface opt in deliberately and consciously.
- Magic methods (`__*`) follow the same "dropped by default, opt-in via
  `includeMagic`" policy for consistency.
