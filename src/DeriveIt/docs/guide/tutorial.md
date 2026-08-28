# Tutorial — From a third-party class to a port you own

This tutorial takes you from *"I depend on someone else's concrete class"* to *"I
own a clean, self-contained port, with an adapter, a fake for tests, and a CI guard
that keeps them honest."* It is deliberately hands-on: every step is runnable.

> **Conventions in this tutorial.** `$` is a shell prompt; `<?php` blocks are PHP you
> run. Output blocks marked *(real)* were produced by the package's own fixtures, so
> you can reproduce them exactly with `php examples/*.php` or `php scripts/derive.php`.

## What you will build

A small application that depends on a `PaymentGateway` — but never on the concrete
vendor class. Instead you depend on `PaymentGatewayInterface`, ship a generated
`PaymentGatewayAdapter` that wraps the vendor class, and write a hand-made
`FakePaymentGateway` for tests. A CI check fails the build if the vendor changes in a
way that would diverge from your generated port.

Prerequisites: PHP ≥ 8.4, `composer require it-pack/derive-it`, and the package
checked out locally for the runnable snippets (`php examples/...`).

---

## Step 1 — Meet the adaptee

Suppose a payment SDK ships this class (you do **not** own it, and you must not edit
it):

```php
namespace Acme\Sdk;

use Acme\Sdk\Contracts\Reusable;

final class PaymentGateway
{
    public function __construct(private readonly string $apiKey) {}

    public static function withDefaultKey(): self { /* ... */ }

    public function charge(int $cents, string $currency): string
    {
        /* talks to the network, returns a transaction id */
    }

    public function refund(string $transactionId): bool { /* ... */ }

    public function lastError(): ?string { /* ... */ }
}
```

Notice what a hand-written interface would force you to *think* about: drop the
constructor (interfaces can't declare one), drop `withDefaultKey()` (static — hard to
mock), decide what to do with the `Reusable` contract the vendor adds later, and keep
the union/intersection types correct. DeriveIt makes those decisions explicit and
reproducible.

Point DeriveIt at it:

```bash
bin/derive 'Acme\Sdk\PaymentGateway'
```

…or, in code:

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::derive(\Acme\Sdk\PaymentGateway::class);
```

---

## Step 2 — Your first port (and what DeriveIt decided)

`DeriveIt::derive()` walks the full class/trait/interface graph and returns the
rendered source of a **self-contained** interface. Here is the *real* output for a
package fixture that mirrors the shape above (`php scripts/derive.php`, target
`_Fixture\UnionTypeClass`):

```php
<?php

namespace _Fixture;

interface UnionTypeClassInterface
{
	function queryLeaf(string $key): array|string|int|float|bool|null;
}
```

*(real)* — the renderer uses `nette/php-generator`, which is why types are faithful and
the method is emitted as an implicit-`public` interface method.

For our `PaymentGateway`, the derived `PaymentGatewayInterface` will declare exactly
`charge()`, `refund()`, and `lastError()` — and **nothing else**:

- `__construct()` is dropped (an interface cannot declare it, and you shouldn't be
  constructing the adaptee through your port).
- `withDefaultKey()` is dropped because it is `static` — a port should be substitutable,
  and a static surface is not.
- The interface `extends` **nothing** of the vendor hierarchy. That is the
  [**leakage invariant**](index.md#leakage-invariant): your port references no vendor
  parent class, trait, or interface. It is enforced in code, not just promised.

> **Why `Interface` suffix?** DeriveIt names the port `<Adaptee>Interface` by default
> (`Naming`). You can rename methods uniformly (see Step 5) but the *type* name follows
> the adaptee so the generated port is easy to find.

---

## Step 3 — Look before you generate

Sometimes you want to *introspect* the surface without emitting source — for tooling,
an IDE, or a "what would this port contain?" check. Use `analyze()` (metadata only) or
`deriveWithResult()` (metadata **plus** rendered source):

```php
use ItPack\DeriveIt\DeriveIt;

$result = DeriveIt::deriveWithResult(\Acme\Sdk\PaymentGateway::class);

$result->fqcn;      // "Acme\Sdk\PaymentGateway"
$result->shortName; // "PaymentGateway"
$result->methods;   // ["charge", "refund", "lastError"]
$result->source;    // the rendered interface source (null under analyze())

// analyze() is the same shape but with ->source === null:
$meta = DeriveIt::analyze(\Acme\Sdk\PaymentGateway::class);
```

`DerivationResult` is an immutable value object: `interface`, `fqcn`, `shortName`,
`source`, `methods`, `substitutions`, `constants`. This is the structured handle the
rest of your tooling should pass around instead of re-parsing PHP.

---

## Step 4 — Shape the surface with `DeriveConfig`

For the common case `derive()` exposes an `includeStatic` shortcut. For *everything
else* you build a `DeriveConfig` and pass it to a config-aware entry point
(`deriveWithResult()`, `deriveAdapter()`, `derivePerTrait()`, `deriveFamily()`,
`analyze()`, `deriveMany()`). This split exists on purpose: the one-line helpers stay
frictionless, while every opinionated choice is an explicit, testable flag.

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

$config = (new DeriveConfig())
    ->withIncludeStatic(true)        // keep withDefaultKey() as a static interface method
    ->withIncludeMagic(true)         // keep __* methods if the vendor uses them
    ->withOnlyMethods(['charge', 'refund']) // port ONLY the calls you actually make
    ->withMethodRenames(['charge' => 'authorize']) // adapt the vendor's vocabulary
;

$source = DeriveIt::deriveWithResult(\Acme\Sdk\PaymentGateway::class, $config)->source;
```

Flags available on `DeriveConfig` (all immutable withers, sane defaults):

| Flag | Default | Effect |
|------|---------|--------|
| `visibility` | `PUBLIC` | `VISIBILITY_PROTECTED` also derives protected methods |
| `includeStatic` | `false` | keep static methods |
| `includeMagic` | `false` | keep `__*` magic methods |
| `dropConstruct` / `dropDestruct` | `true` | drop lifecycle methods |
| `typeSubstitutionMap` | `[]` | `FQCN ⇒ derived-interface FQCN` rewrite |
| `substituteSelfStatic` | `true` | rewrite `self`/`static`/`parent` return types |
| `includeConstants` | `false` | lift constants into the interface |
| `onlyMethods` / `skipMethods` | `[]` | allow-list / deny-list by name (mutually exclusive) |
| `methodRenames` | `[]` | `original ⇒ new` name, applied uniformly |
| `failOnEmpty` | `false` | throw instead of emitting an empty interface |
| `attributePassThrough` / `ownedAttributes` | `false` / `[]` | copy attributes (all, or owned only) |
| `preserveDocblocks` / `throwsCapture` | `false` | copy `@param/@return/@throws` phpdoc |
| `auditLeakage` | `false` | enable the deep leakage auditor (see the Cookbook) |
| `reExposeOwnedInterfaces` | `[]` | explicitly re-`extends` interfaces *you* own |

Three presets bundle common stances: `DeriveConfig::strict()` (audit + fail-on-empty),
`forAdapter()` (static on, since adapters delegate everything), `forFamily()`
(substitution on for cross-references).

> **Segregate a fat class.** If the vendor class is large but composed of traits, derive
> one interface *per trait* and compose them: `DeriveIt::derivePerTrait()` returns the
> composed main interface; `DeriveIt::deriveTraitInterfaces()` returns each trait
> interface keyed by trait short name. This is Interface Segregation, applied by the
> tool itself.

---

## Step 5 — Generate the adapter

The interface is the *contract*; the **adapter** is the *bridge* to the vendor. One call
emits both in a single, self-contained file:

```php
use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveAdapter(\Acme\Sdk\PaymentGateway::class);
```

Here is the *real* output for the package fixture `_Fixture\Service`
(`php examples/06-adapter-generation.php`, edited for brevity):

```php
<?php

namespace _Fixture;

interface ServiceInterface
{
	function name(): string;
	function collaborator(): Collaborator;
	function maybe(): ?Collaborator;
	function reset(): void;
}

final class ServiceAdapter implements ServiceInterface
{
	private readonly Service $adaptee;

	public function __construct(Service $adaptee)
	{
		$this->adaptee = $adaptee;
	}

	public function name(): string
	{
		return $this->adaptee->name();
	}
	// … every interface method forwards to $this->adaptee …
}
```

*(real)*

The adapter holds the adaptee via **constructor injection** and forwards every method.
Wire it into your container:

```php
// config/container.php
$container->set(PaymentGatewayInterface::class, fn () =>
	new PaymentGatewayAdapter(new \Acme\Sdk\PaymentGateway($_ENV['API_KEY']))
);
```

Your application code type-hints `PaymentGatewayInterface` and never names the vendor
class. That is the whole point: the vendor is now a *detail* behind a port you own.

> **Wrapping returned ports.** If a method returns *another* derived type (e.g.
> `Tree::getRoot(): Node`), pass `returnWrapperMap: [NodeInterface::class => NodeAdapter::class]`
> so the adapter converts the concrete return value into its port too, keeping your
> port free of vendor types. See the Cookbook's family section.

---

## Step 6 — Write a fake for tests

You do **not** need DeriveIt to write a test double — the port is ordinary PHP:

```php
final class FakePaymentGateway implements PaymentGatewayInterface
{
    public array $charges = [];

    public function charge(int $cents, string $currency): string
    {
        $this->charges[] = [$cents, $currency];
        return 'tx_' . count($this->charges);
    }

    public function refund(string $transactionId): bool { return true; }
    public function lastError(): ?string { return null; }
}
```

Because `PaymentGatewayInterface` is generated from the *real* vendor surface, the fake
is forced to stay in sync with what the adapter promises. If the vendor adds a method,
both your adapter and your fake fail to compile until you handle it — a compile-time
contract, not a runtime surprise.

---

## Step 7 — Keep the port honest in CI

Vendors change. DeriveIt is **deterministic**, so a port derived from an unchanged
adaptee reproduces byte-for-byte. `DeriveIt::diff()` compares a freshly derived port
against the one you committed:

```php
use ItPack\DeriveIt\DeriveIt;

$committed = file_get_contents(__DIR__ . '/ports/PaymentGatewayInterface.php');
$diff = DeriveIt::diff(\Acme\Sdk\PaymentGateway::class, $committed);

if ($diff->drift) {
    echo "Port drifted from vendor:\n", $diff->diff;
    exit(1);
}
```

`DeriveDiff` carries `drift` (bool), `diff` (a unified-style line diff), and the two
sources. Wire that into a CI job and you get a *reviewable* signal the moment the vendor
shifts its public surface — before it breaks production. The companion script
`scripts/regenerate_goldens.php` regenerates committed ports when a drift is intentional;
review the diff, commit, move on.

---

## Step 8 — Where to go next

- **Cookbook** (`cookbook.md`) — advanced patterns: family derivation + namespace
  rewriting, the deep leakage auditor, telemetry/observability, performance at scale,
  and the full CLI.
- **Architecture** (`architecture.md`) — how the graph walker, filters, signature
  factory, and derivers fit together.
- **Features** (`features.md`) and the runnable **examples** (`examples/`) — one script
  per capability.
- **Design decisions** (`../architecture/decisions/`) — why Nette types the grammar
  (ADR-0001), why per-trait composition (ADR-0002), the static-method policy
  (ADR-0003), and FQCN-canonical type substitution (ADR-0004).

You now own a port, an adapter, a fake, and a guardrail — and none of it was written by
hand.
