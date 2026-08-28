# Cookbook — Advanced adoption patterns

The [tutorial](tutorial.md) gets you to a working port. This cookbook is the
*sophisticated* half: the patterns you reach for once DeriveIt is part of how your
codebase manages third-party and legacy dependencies at scale. Every pattern is
runnable and references a real API entry point.

## The mental model (read this first)

Three ideas make the patterns below coherent:

1. **A port is something you own; an adaptee is a detail.** DeriveIt produces the port
   (`<Adaptee>Interface`) and, optionally, a delegating `Adapter`. Your code depends on
   the port. Swapping vendors, faking for tests, and auditing the boundary all become
   local, mechanical operations.
2. **The leakage invariant is a guarantee, not a style.** In flat mode the derived
   interface has an *empty* `extends` and emits no `use`/FQCN from the adaptee's own
   hierarchy; in per-trait mode it `extends` only the derived trait interfaces. This is
   asserted in code (`InterfaceDeriver::assertSelfContained()`,
   `PerTraitInterfaceDeriver::assertPerTraitSelfContained()`), so a leaky port fails
   fast rather than shipping silently. See `features.md §9` and `examples/09`.
3. **Derivation is deterministic.** The same adaptee + config always yields the same
   bytes. That single property is what makes committed ports *reviewable contracts* and
   what powers CI drift detection (`DeriveIt::diff()`, `scripts/regenerate_goldens.php`).

With those in hand, the rest is composition.

---

## Pattern A — Adopt a vendor behind a port (the baseline)

```php
use ItPack\DeriveIt\DeriveIt;

file_put_contents(
    'ports/PaymentGatewayInterface.php',
    DeriveIt::derive(\Acme\Sdk\PaymentGateway::class),
);

// Container: bind the port to the generated adapter.
$container->set(
    \App\Port\PaymentGatewayInterface::class,
    fn () => new \App\Port\PaymentGatewayAdapter(new \Acme\Sdk\PaymentGateway($key)),
);
```

Your application type-hints `PaymentGatewayInterface` and never names `Acme\Sdk`. When
the vendor is replaced, only the adapter (and the `new` in the container) change.

---

## Pattern B — The adapter lifecycle (generate → wire → swap)

Adapters are *generated once and committed*, then treated as normal source. The lifecycle:

1. **Generate** the interface + adapter into your `ports/` tree
   (`DeriveIt::deriveAdapter()` or `bin/derive --mode=adapter --out=ports/`).
2. **Wire** the adapter in the container (Pattern A).
3. **Swap adaptees** with zero changes to call sites — because call sites depend on the
   port, not the adapter. To move from `Acme\Sdk` to `Beta\Pay`, you write a new
   `BetaPaymentGatewayAdapter implements PaymentGatewayInterface` and rebind the
   container; no calling code moves.

When a returned value is itself a derived type, keep the port concrete-free with a
wrapper map:

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(
    \App\Tree::class,
    returnWrapperMap: [
        \App\NodeInterface::class => \App\NodeAdapter::class,
    ],
);
// TreeAdapter::add(NodeInterface $node) { $this->adaptee->add(new NodeAdapter($node)); }
```

*(see `examples/06-adapter-generation.php` and `features.md §6`)*.

---

## Pattern C — Phased adoption (the strangler)

You rarely need *all* of a fat vendor class on day one. Derive only what you call, then
grow the port as you adopt more:

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = (new DeriveConfig())
    ->withOnlyMethods(['charge', 'refund']);   // phase 1 surface only

$source = DeriveIt::deriveWithResult(\Acme\Sdk\PaymentGateway::class, $config)->source;
```

`onlyMethods` and `skipMethods` are mutually exclusive (DeriveIt throws if a name appears
in both). Add methods to `onlyMethods` in later phases; the CI drift check (Pattern H)
tells you when the vendor surface moved. This is the safest way to port a legacy
dependency without a big-bang rewrite.

---

## Pattern D — Segregate a fat class with per-trait interfaces

If the adaptee is composed of traits, derive one interface per trait and let the main
interface `extends` exactly those:

```php
use ItPack\DeriveIt\DeriveIt;

$mainSource     = DeriveIt::derivePerTrait(\App\UsesTraits::class);
$traitInterfaces = DeriveIt::deriveTraitInterfaces(\App\UsesTraits::class);
// $traitInterfaces['LoggerTrait']->hasMethod('log');
```

Conflict rule: when a method name appears in multiple traits, the first trait in
`getTraitsRecursively()` order owns it; the others drop it — so the composed interface can
never declare a duplicate. Empty traits (no public methods) are skipped. This is
Interface Segregation, applied by the tool (ADR-0002).

---

## Pattern E — Derive a family and rewrite cross-references

Cooperating types (`Node` ⇄ `Tree`) must reference *each other's ports*, not the concrete
classes. Derive them together so the substitution map is auto-built:

```php
use ItPack\DeriveIt\DeriveIt;

$sources = DeriveIt::deriveFamily([
    \App\Node::class,
    \App\Tree::class,
]);
// NodeInterface::getNext(): ?NodeInterface   (was ?Node)
// TreeInterface::getRoot(): NodeInterface    (was Node)
```

*(real shape from `examples/05-family-substitution.php`)*.

For an **entire module reachable from one root**, auto-discover the family:

```php
$sources = DeriveIt::deriveFamilyFromRoot(\App\Payments\Gateway::class);
```

And to **place every port under its own namespace** (so the boundary is visible in the
file tree), rewrite namespaces with a prefix map:

```php
$sources = DeriveIt::deriveFamilyFromRoot(
    \App\Payments\Gateway::class,
    namespacePrefixMap: ['App\\' => 'App\\Port\\'],
);
// ports render into App\Port\… instead of App\Payments\…
```

`deriveFamilySource()` returns one grouped source string; `deriveFamilyAdapter()` emits a
delegating adapter per member, auto-wiring each member's returned port to its sibling
adapter. For types *outside* the family, supply an explicit `typeSubstitutionMap`:

```php
$config = (new DeriveConfig())->withTypeSubstitutionMap([
    \Acme\Sdk\Money::class => \App\Port\MoneyInterface::class,
]);
$source = DeriveIt::deriveAdapter(\App\Tree::class, config: $config);
```

---

## Pattern F — Re-expose owned interfaces without leaking

By default the port `extends` nothing of the adaptee. Sometimes the adaptee implements an
interface **you own** (your own `Identifiable` contract) and you *want* that on the port.
That is the one sanctioned widening of the invariant — opt in by FQCN:

```php
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = (new DeriveConfig())->withReExposeOwnedInterfaces([
    \App\Contracts\Identifiable::class,
]);
```

Third-party interfaces must **not** be listed here; doing so would defeat the leakage
invariant. If you need to *verify* a port is clean before publishing it downstream, turn
on the deep auditor.

---

## Pattern G — The deep leakage auditor (strict ports)

`assertSelfContained()` only forbids `extends`. The optional deep auditor
(`LeakageAuditor`) goes further: it flags any *foreign* (non-owned) type appearing in a
signature — the strict guarantee you want before a port is consumed by another team or
published as a library.

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = DeriveConfig::strict();  // auditLeakage=true + failOnEmpty=true
// or: (new DeriveConfig())->withAuditLeakage(true)

$source = DeriveIt::deriveWithResult(\Acme\Sdk\PaymentGateway::class, $config)->source;
```

Enable it only once the derive set / substitution map is complete: an unmapped foreign
return type (a vendor type you haven't ported) is exactly what the auditor is built to
catch. Pair it with `reExposeOwnedInterfaces` (Pattern F) for the interfaces you do own.

---

## Pattern H — Drift detection as a CI guardrail

Determinism is the lever. Commit your ports, then fail CI on divergence:

```php
use ItPack\DeriveIt\DeriveIt;

foreach (PORTS as $fqcn => $file) {
    $diff = DeriveIt::diff($fqcn, file_get_contents($file));
    if ($diff->drift) {
        fwrite(STDERR, "Drift in {$fqcn}:\n{$diff->diff}");
        exit(1);
    }
}
```

When a drift is intentional (the vendor legitimately changed), regenerate with
`scripts/regenerate_goldens.php`, review the diff, and commit. The port becomes a
reviewable artifact, not a hidden assumption.

---

## Pattern I — Observability via the telemetry port

Derivations emit **facts only** (activities, measurements, observations, occurrences)
through `DeriveEventSinkInterface` — no timing, no identity, no context storage (those
are host concerns). Wire a real sink to watch the pipeline, or attach derivations to a
[RunIt](../integrations/runit.md) execution via the shipped bridge:

```php
use ItPack\DeriveIt\DeriveIt;
use App\Telemetry\MyEventSink;

$result = DeriveIt::deriveWithResult(
    \Acme\Sdk\PaymentGateway::class,
    config: new \ItPack\DeriveIt\Generator\DeriveConfig(),
    eventSink: new MyEventSink(),   // activityStarted/Finished, measured, observed, occurred
);
```

The vocabulary (event / measurement / unit names) comes from
`Contract\Telemetry\DeriveEvents|DeriveMeasurements|DeriveUnits` — never inline strings.
On the CLI, `--verbose` streams the same facts to **stderr** (stdout stays byte-identical
to a non-verbose run), which is what you pipe to a log shipper.

---

## Pattern J — Performance at scale

Deriving hundreds of types in one process (a bootstrap scan, a codegen tool) should not
re-reflect each class repeatedly. Two levers:

**Reflection caches.** Swap the default in-memory cache for a PSR-6 or weak-reference
cache at the composition root:

```php
use ItPack\DeriveIt\DeriveItFactory;
use ItPack\DeriveIt\Reflection\PsrCacheReflectionCache;
use ItPack\DeriveIt\Reflection\WeakReflectionCache;

$factory = new DeriveItFactory(
    new \ItPack\DeriveIt\Generator\DeriveConfig(),
    reflectionCache: new PsrCacheReflectionCache($psr6Cache), // cross-request reuse
    // or: new WeakReflectionCache(),                          // long-running worker
);

$interface = $factory->interfaceDeriver()->derive(\App\Widget::class);
```

`InMemoryReflectionCache` (default), `PsrCacheReflectionCache` (shared/cross-process),
and `WeakReflectionCache` (bounded in a long-lived process) all implement
`ReflectionCacheInterface`.

**Batch, don't loop.** `DeriveIt::deriveMany($classes, $config)` derives a set and returns
a `DerivationResult` per FQCN (each carrying rendered source), sharing one deriver — and
one cache — across the run. The factory also exposes a single shared `SourceRenderer` and
`DeriveEventEmitter`, so a codegen tool should obtain them from `DeriveItFactory` rather
than constructing its own.

---

## Pattern K — Shape the vocabulary and metadata

Beyond method selection, DeriveIt can adapt the *spelling* and *documentation* of a port:

- **`methodRenames`** — rename uniformly across flat/per-trait/family (`charge` ⇒
  `authorize`) so the port reads like your domain, not the vendor's.
- **`includeConstants`** — lift the adaptee's constants into the interface.
- **`attributePassThrough` + `ownedAttributes`** — copy PHP attributes (all, or only
  attributes you own) onto the derived methods.
- **`preserveDocblocks`** — copy `@param`/`@return`/`@throws`/`@template`/`@deprecated`
  phpdoc, with FQCN types simplified through the substitution map.
- **`throwsCapture`** — capture `@throws` from the adaptee phpdoc into the port.

All are immutable withers on `DeriveConfig`, so a config is a value you can store,
diff, and reuse across a team.

---

## CLI automation reference

`bin/derive` drives the whole API without writing PHP. Modes: `flat` (default),
`per-trait`, `adapter`, `family`, `family-adapter`. Key options:

```bash
# single port to stdout
bin/derive 'App\Payments\Gateway'

# per-trait, written into a ports directory
bin/derive --mode=per-trait 'App\Payments\Gateway' --out=ports/

# auto-discover a family, rewrite into App\Port\, sandbox the output
bin/derive --root='App\Payments\Gateway' --mode=family \
           --ns-prefix='App\=App\Port\' --out=ports/ --base-dir=ports/

# adapter with a return-type wrapper map and a preset
bin/derive --mode=adapter 'App\Mail\Sender' \
           --wrapper='App\Mail\MessageInterface=App\Mail\MessageAdapter' \
           --preset=adapter --out=SenderAdapter.php

# strict, verbose (facts to stderr), drift-relevant configuration
bin/derive --mode=flat 'App\Vendor\Thing' --audit-leakage --fail-on-empty --verbose
```

Notable flags: `--include-static`, `--include-magic`, `--include-constants`,
`--protected`, `--preserve-docblocks`, `--attribute-pass-through`, `--owned-attr`,
`--skip`, `--only`, `--subst` (`Orig=Port`), `--rename`, `--re-expose`, `--wrapper`,
`--ns-prefix`, `--base-dir` (lexical sandbox for `--out`), `--preset`, `--verbose`,
`--help`.

> **Trust model (F13).** The CLI autoloads the class names / token-scans the files you
> pass. Run it only on code you trust. `--base-dir` is a lexical guard; it does not
> resolve symlinks in the target path, so point `--out` only at paths you control.

---

## Anti-patterns

- **Don't hand-edit a generated port** and then re-derive over it — the drift check will
  flag your edits. Either regenerate deliberately (`regenerate_goldens.php`) or keep
  generated ports in their own directory and layer hand-written code on top.
- **Don't derive with `includeStatic(true)` by reflex.** A static surface is hard to
  substitute; keep it opt-in unless the adaptee is genuinely a static facade you must
  mirror (PHP ≥ 8.4 allows static interface methods).
- **Don't list third-party interfaces in `reExposeOwnedInterfaces`.** That defeats the
  leakage invariant; the deep auditor (Pattern G) exists to catch exactly this.
- **Don't loop `derive()` in a codegen tool** — batch with `deriveMany()` and a shared
  cache (Pattern J) so reflection happens once per type.

## Related reading

- [Tutorial](tutorial.md) — the end-to-end walkthrough.
- [Features](features.md) — every capability, with an example each.
- [Architecture](architecture.md) — graph walker → filters → signature factory → derivers.
- [RunIt integration](../integrations/runit.md) — attach derivations to an execution.
- [Design decisions](../architecture/decisions/) — ADR-0001…0004.
