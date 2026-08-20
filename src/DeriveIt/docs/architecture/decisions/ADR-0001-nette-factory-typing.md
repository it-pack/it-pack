# ADR-0001: Use `nette/php-generator` `Factory::fromMethodReflection()` for typing

- **Status:** Accepted
- **Date:** 2026-08-14

## Context

Deriving an interface means reproducing each method's full signature: union and
intersection types, nullability, variadics, by-reference parameters, readonly,
constructor promotion, and enums as parameter/return types. Hand-building
`Nette\PhpGenerator\Parameter`/`Method` objects from `ReflectionMethod` would
re-implement PHP's entire type grammar and inevitably miss edge cases.

## Decision

Use `Nette\PhpGenerator\Factory::fromMethodReflection()` as the single source of
truth for parameter and return typing. `derive-it` does not hand-construct type
grammar; it maps a `ReflectionMethod` through the factory, then forces the result
to be `abstract` + `public` with no body for interface methods.

## Consequences

- Correct handling of union/intersection/nullable/variadic/by-ref/readonly/enums
  is inherited from Nette, not maintained locally.
- The factory returns fully-typed `Method` objects, so the deriver only decides
  *which* methods to include and *how* to shape the `InterfaceType`.
- Nette renders FQCN type strings as clean short names in source, which drives the
  canonical-type decision in ADR-0004.
