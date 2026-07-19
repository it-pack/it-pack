PIPELINE.md

This is arguably the heart of ConfigureIt.

The entire library can be understood as a configurable pipeline.

Contents
1. Philosophy

2. Pipeline Stages

3. Processor Lifecycle

4. Execution Order

5. Error Handling

6. Custom Processors

7. Examples

8. Future Extensions

Pipeline:

Discovery

↓

Source

↓

Reader

↓

Loader

↓

Document

↓

Normalization

↓

Merge

↓

Validation

↓

Interpolation

↓

Variable Resolution

↓

Transformation

↓

Defaults

↓

Freeze

↓

Snapshot

Each stage explains:

Input
Output
Responsibilities
Replaceability