# ADR-015: Concrete classes are final and interface-backed

**Status:** Accepted

Production concrete classes are `final` and implement focused contracts.
Composition is preferred over inheritance; immutable classes use `readonly`.
