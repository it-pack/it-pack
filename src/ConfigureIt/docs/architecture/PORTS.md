PORTS.md

The public contracts of ConfigureIt.

This document answers:

What are the stable extension points?

Contents
1. Philosophy

2. Core Ports

   ConfigurationSource
   ConfigurationReader
   ConfigurationLoader
   ConfigurationResolver
   ConfigurationSchema
   ConfigurationProcessor
   ConfigurationMerger
   ConfigurationCache
   ConfigurationGraphBuilder
   ConfigurationSnapshotFactory

3. Port Relationships

4. Lifecycle

5. Dependency Rules

6. Extension Guidelines

7. Examples

For every port:

Purpose
Responsibilities
What it may depend on
What it may not depend on
Typical adapters
Example implementation

Example:

ConfigurationLoader

Responsibility

Resource
↓
ConfigurationDocument

Must not:

- know about ENV
- know about merging
- know about schemas
- know about pipelines

Only parsing.