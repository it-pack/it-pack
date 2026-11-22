# RunIt Architecture Dependency Inventory

This is a source-based inventory, not a generated dependency graph or a new
architecture policy. The executable rules are in `phparkitect.php`; the shared
helper is `../../tools/qa/phparkitect/base.php` relative to the package root.
`composer architecture` analyzes `src/` only, not tests or examples.

## Effective Component Rules

The table describes permitted dependencies, not necessarily actual edges. Same-component
dependencies are also permitted. `Contract` includes `Contract\Port`; adapters
are one component, and `Conformance` is production source, not test support.

| Component | Other declared components allowed by the current gate |
|---|---|
| Common | none |
| Contract | Common |
| Domain | Common, Contract, BehaviorDomain |
| BehaviorDomain | Common, Contract, Domain |
| Application | Common, Contract, Domain, BehaviorDomain, BehaviorApplication |
| BehaviorApplication | Common, Contract, Domain, BehaviorDomain, Application |
| SDK | Common, Contract, Domain, BehaviorDomain, Application, BehaviorApplication |
| Adapter | Common, Contract, Domain, BehaviorDomain |
| Conformance | all other declared components |

The helper uses PHPArkitect's `mayDependOnComponents`: the installed
implementation generates prohibitions against the *other declared components*.
It does not use `shouldOnlyDependOnComponents`. Consequently a green run does
not prove that dependencies on external namespaces or undeclared RunIt namespaces
are absent, nor that every source class is selected by a component. It also does
not distinguish dependencies between ports and other contracts. The table does
not claim enforcement of the stronger vendor-neutrality policy in the architecture
prose. PHPStan has additional import rules; their scope is separate from this gate.

## Observed Edges And Open Decisions

Representative source imports, checked on 2026-11-22, demonstrate why the
layer decision must precede changes to the rules:

- `Domain/Execution/ExecutionId.php` imports
  `Behavior\Domain\ValidatedStringIdentifierBehavior`, which imports
  `Domain\Shared\StringIdentifierValidator` and
  `Domain\Exception\InvalidIdentifierException`: both directions exist.
- `Application/Publication/DefaultObservationClassifier.php` imports
  `Behavior\Application\Publication\DropsWithHealthReport`, which imports
  `Application\Factory\RedactedRedactedArtifactFactory` and
  `Application\Publication\FallbackErrorLogReporter`: both directions exist.
- `Contract/Support/ActivityContextStorage.php` imports three `Contract\Port`
  interfaces. The former diagram's separate Contract and Port directions cannot
  be assumed to describe the current code or current enforcement.
- `Adapter/OpenTelemetry/BridgeConfig.php` imports OpenTelemetry APIs, and
  `Adapter/CloudEvents/Sink/Psr18CloudEventSink.php` imports PSR HTTP APIs.
  These are examples, not a complete external-dependency inventory.

Decisions remain open on whether ports should be an independently constrained
component, whether Behavior namespaces are separate layers or parts of their
corresponding layers, and how vendor and unclassified-namespace boundaries should
be enforced. Assess actual dependencies, compatibility and ADRs before changing
policy. Negative architectural regression fixtures are still needed once the
intended rules are agreed; a passing current-source scan is not a negative test.

## Evidence

On 2026-11-22, `composer architecture` with PHPArkitect 1.3.1.0 analyzed
443 classes and reported no violations under the current rules. The historical
DepTrac diagram, edge counts and uncovered-dependency count were removed because
they are not a current PHPArkitect report.

On the installed dependency set, `composer audit` and `composer audit --no-dev`
reported no security advisories. The full audit reported abandoned
`doctrine/annotations` 2.0.2, with no suggested replacement. `composer why
doctrine/annotations --tree` traced it through `phpbench/phpbench` 1.7.1 and
`just-bases/php-qa-base` 1.0.9, a RunIt development dependency. The configured
`composer security:audit:full` also passed while reporting abandonment. An
abandoned package is a maintenance concern, not itself a vulnerability; reassess
the upstream dependency chain on future updates rather than adding an unsupported
pin. These results are time- and installed-dependency-specific.

For a component-level runtime view, see
[`runit-runtime.html`](../diagram/runit-runtime.html).
