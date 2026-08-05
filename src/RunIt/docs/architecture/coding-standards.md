# RunIt Coding and Architecture Standards

RunIt uses the execution vocabulary defined in `docs/EXECUTION.md`. The domain
is vendor-neutral and depends only on RunIt contracts; application code depends
inward; adapters translate external systems through ports.

Every PHP file declares strict types. Concrete production classes are `final`,
implement a focused contract, and are `readonly` unless controlled lifecycle
mutation is required. Identifiers, names, units, and other validated concepts
use immutable value objects. Builders are immutable and return a changed copy.

Public interfaces are small, explicitly typed, and stable. Exceptions implement
the applicable RunIt marker interface. Normalization and serialization are
explicit, deterministic, bounded, and must never use arbitrary object
serialization. Global mutable execution state and framework dependencies in the
core are prohibited.

PHPDoc generics supplement, but never contradict, native types. Experimental
public APIs must be documented as experimental until their compatibility policy
is accepted.
