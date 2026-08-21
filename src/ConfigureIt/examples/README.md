# ConfigureIt Examples

Runnable, copy-paste demos of ConfigureIt's features. Every script runs from the
package root with plain PHP — no SDK, no cloud, no framework:

```bash
php examples/<name>.php
```

Each example is paired with committed fixtures under `examples/Support/` so its
output is deterministic.

---

## Basics

| Example | Shows |
|---|---|
| [`resolve.php`](resolve.php) | The hello-world: discover files, parse through the pipeline, freeze an immutable snapshot. |
| [`env.php`](env.php) | Loading configuration from environment variables via `EnvSource` / `EnvReader` / `EnvLoader`. |
| [`dotenv.php`](dotenv.php) | The Dotenv adapter (`vlucas/phpdotenv`) loading a `.env` file as a configuration source. |
| [`02-formats.php`](02-formats.php) | **Formats are adapters.** One logical config sharded across PHP, JSON, YAML, TOML, INI, XML and Markdown-Front-Matter, merged by a single API. |

## Composition

| Example | Shows |
|---|---|
| [`04-schema-validation.php`](04-schema-validation.php) | **Schemas define truth.** A Nette Schema (behind the `ConfigurationSchema` port) validates the graph and fails fast with a precise message. |
| [`06-variables-includes.php`](06-variables-includes.php) | **Pipelines create knowledge.** `IncludeProcessor` (`@include`) pulls keys from another file; `VariableProcessor` resolves `${dotted.key}` placeholders. |
| [`provenance.php`](provenance.php) | Per-node provenance, diagnostics observations and graph diffing (where each value came from, and why). |

## Operations

| Example | Shows |
|---|---|
| [`08-observability.php`](08-observability.php) | **Configuration is observable.** Native OpenTelemetry traces, lifecycle events and metrics built on the Execution reporter spine; inspectable offline via `InMemoryTelemetrySink`, with the real-SDK seam shown via a stub `OpenTelemetryBridgeInterface`. |

---

## Suggested reading order

1. `resolve.php` — the shape of the API.
2. `02-formats.php` — why "formats are adapters" matters.
3. `04-schema-validation.php` — constraining configuration.
4. `06-variables-includes.php` — composing a real pipeline.
5. `provenance.php` — explainability.
6. `08-observability.php` — instrumenting resolution.

## CLI (Phase 7, not a PHP example)

The `bin/configure-it` dispatcher operates on single files through the public
reader/loader/normalizer ports — no builder required:

```bash
bin/configure-it explain examples/Support/Provenance/local.json app.debug
bin/configure-it diff    examples/Support/Provenance/base.json examples/Support/Provenance/local.json
bin/configure-it graph   examples/Support/Provenance/local.json ascii
```

See `docs/ROADMAP.md` (Phases 2–9) for the full feature catalogue behind each
example.
