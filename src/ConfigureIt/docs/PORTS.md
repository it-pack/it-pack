# Ports & Adapters — surface map

ConfigureIt is hexagonal: every cross-boundary capability is expressed as a
`Contract` interface (a **port**), and the `Adapter` / `Bridge` layers supply
the concrete implementations. This file is the authoritative list of which
concretes are **open ports** (depend-on-the-interface) versus **blessed
internals** (adapter-private helpers that are intentionally not ported).

## Open ports (Milestone 5 extraction — NV8)

These services now expose a contract in `src/Contract/` (or
`src/Contract/ValueObject/`) and every concrete `implements` it. Dependents must
type-hint the interface, never the concrete.

| Contract | Concrete | Layer |
|----------|----------|-------|
| `MessageFactoryInterface` | `Adapter\MessageFactory` | Adapter |
| `ConfigurationLoadersInterface` | `Adapter\ConfigurationLoaders` | Adapter |
| `ConfigurationBuilderInterface` | `Core\ConfigurationBuilder` | Core |
| `ReloadableConfigurationInterface` | `Core\ReloadableConfiguration` | Core |
| `ConfigurationDifferInterface` | `Core\ConfigurationDiffer` | Core |
| `ConfigurationDiagnosticsInterface` | `Core\ConfigurationDiagnostics` | Core |
| `ConfigurationDiffInterface` | `ValueObject\ConfigurationDiff` | ValueObject |
| `DiagnosticsReportInterface` | `ValueObject\DiagnosticsReport` | ValueObject |
| `FlagDefinitionInterface` | `ValueObject\FlagDefinition` | ValueObject |
| `ConfigurationGraphRewriter` (composition seam) | `Core\ConfigurationGraphRewriter` | Core |
| `ConfigurationTelemetrySinkInterface` (NV9b) | OTel SDK-backed impl wired only in `Wiring\DefaultConfigurationWiring`; consumed by `Bridge\OpenTelemetry\OpenTelemetryReporter` | Contract → Bridge/Wiring |

The `ConfigurationWiringInterface` gained `graphRewriter()` so node-rewriting
processors receive the rewriter collaborator via dependency injection rather
than constructing it internally (NV7 / NV10).

## Blessed internals (not ports)

These are adapter-internal helpers. They are not interface-backed by design
(REVIEW R17 / NV8 triage): they encode Format- or SDK-specific mechanics and are
not meant to be swapped by consumers.

- `CommandDecryption` (adapter-internal helper; `DecrypterInterface` is the port)
- `ScalarCaster`, `RedactingRenderer`, `ConfigurationCompiler`, `Key`,
  `FileModificationProbe`, `TreeBuilder`, `HttpResponse`, `FeatureFlagSchema`,
  `SemanticConventions`

## Exempt boundaries

- **CLI boundary** (`Output`, `ConfigurationInspector`, `GraphFromFile`,
  `GraphVisualizer`, `ConfigurationCli`) — entrypoints, not swappable services.
- **Codegen internals** (`SchemaModelBuilder`, `ConfigCodegen`,
  `ConfigDtoHydrator`, `PhpRenderer`) — code-generation assembly, not runtime
  ports.

## Vocabulary home rule (NV13)

- A **single-use** literal becomes a class constant.
- A **shared / cross-layer** vocabulary becomes an interface constant or a
  `Contract` enum (`OccurrenceType` is the canonical example — call sites use
  `OccurrenceType::…->value`, never the raw string).
