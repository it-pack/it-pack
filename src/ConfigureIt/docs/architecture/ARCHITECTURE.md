### ARCHITECTURE.md

## Purpose

This document describes the architectural philosophy behind ConfigureIt.

ConfigureIt is intentionally not a configuration parser.

Instead, it is a Configuration Resolution Engine that orchestrates configuration from arbitrary sources through a deterministic, extensible pipeline.

Its architecture is based on four principles:

Hexagonal Architecture
SOLID
Dependency Inversion
Composition over Inheritance
High-Level Architecture
Application
│
Configuration Facade
│
Configuration Builder
│
Resolution Pipeline Engine
│
┌─────────────────────────────────┐
│ Sources │ Loaders │ Processors │
└─────────────────────────────────┘
│
Configuration Graph
│
Immutable Configuration
Snapshot
Layers
Contracts

Pure interfaces and value objects.

Contains no implementation.

Examples:

ConfigurationSource
ConfigurationLoader
ConfigurationResolver
Core

Business rules.

Knows nothing about external libraries.

Contains:

Resolver
Builder
Graph
Pipeline
Snapshot
Adapters

Infrastructure implementations.

Examples:

YAML
TOML
NEON
Dotenv
Aura.Di
Bridges

Optional integrations.

Examples:

Symfony
Laravel
Aura.Di
Resolution Pipeline
Source

↓

Reader

↓

Loader

↓

Document

↓

Normalizer

↓

Merge

↓

Validation

↓

Variable Resolution

↓

Defaults

↓

Graph

↓

Snapshot

Each stage is independently replaceable.

Configuration Graph

Internally every configuration value should become a node.

Nodes know:

source
priority
provenance
schema
metadata

This graph eventually enables diagnostics and explainability.