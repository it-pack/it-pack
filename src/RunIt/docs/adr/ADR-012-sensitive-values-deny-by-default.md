# ADR-012: Sensitive values are deny-by-default

**Status:** Accepted

Secrets are never exported. Classification, redaction, payload limits, and
cardinality controls apply before any adapter receives an artifact.
