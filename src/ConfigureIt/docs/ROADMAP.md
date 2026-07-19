Vision

Become the most extensible configuration platform available for PHP.

Phase 0 ✅ (complete)

Repository Bootstrap

Project structure ✅
CI
Coding standards ✅
PHPStan ✅ (level 9, 0 errors)
PHPUnit — Codeception Unit suite used instead
Psalm
Rector
Phase 1 ✅ (complete)

Core

Deliver:

Contracts ✅
Sources ✅ (FileSource, EnvSource)
Loaders ✅ (PhpLoader, JsonLoader)
Builder ✅
Resolver ✅
Snapshot ✅

Supported formats:

PHP ✅
JSON ✅
Phase 2

File Formats

YAML
TOML
NEON
XML
Front Matter
Phase 3

Schemas

Integrate:

Nette Schema

Support:

validation
defaults
type coercion
Phase 4

Pipeline

Processors

Includes
Imports
Merge
Variables
Defaults
Validation
Casting
Phase 5

Adapters

Dotenv
Aura.Di
Vault
Redis
HTTP
Phase 6

Graph

Introduce

provenance
diagnostics
semantic graph
graph diffing
Phase 7

Developer Experience

CLI
Inspector
Explain command
Graph visualization
Configuration diff
Phase 8

Enterprise

Kubernetes
ConfigMaps
Secrets
AWS
Azure
GCP
Phase 9

Observability

Native OpenTelemetry instrumentation.

Metrics

Tracing

Configuration lifecycle events.