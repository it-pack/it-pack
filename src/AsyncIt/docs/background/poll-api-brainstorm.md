Yes — and I think there are three quite different strategies, with one looking particularly attractive for AsyncIt development.

Linux polyfill: probably not feasible in the true sense.
Custom PHP build: technically clean, but annoying for your normal host PHP.
OCI/containerized PHP 8.6-dev build: probably the best development/conformance strategy.

The important distinction is what we mean by "polyfill."

The Poll API is below userland PHP

If the RFC introduces functionality at the PHP runtime/extension level, a Composer package cannot genuinely polyfill it:

PHP userland
│
├── ReactPHP
├── Amp
├── AsyncIt
└── your "polyfill"
│
X
Poll API
│
PHP engine
│
OS

A PHP package can emulate the semantic interface, perhaps using stream_select(), but it cannot provide the actual native Poll API to an extension or code that expects the PHP engine's implementation.

So I'd distinguish:

Poll API compatibility
≠
Poll API implementation

A userland compatibility layer could be useful for AsyncIt's portable fallback, but shouldn't pretend to be the PHP 8.6 Poll API.

The interesting option: a Poll API adapter

For AsyncIt, I actually think this is better than trying to "polyfill PHP."

Define internally:

AsyncIt
│
▼
Poller contract
│
├── NativePoller      PHP 8.6+
│
└── SelectPoller      PHP 8.5/Linux fallback

Then the development environment can test both.

Conceptually:

                     AsyncIt
                        │
                    PollerPort
                  ┌─────┴─────┐
                  │           │
             NativePoll    CompatPoll
                  │           │
             PHP Poll API  stream_select()

That is exactly the kind of adapter boundary we want to discover during the AsyncIt research anyway.

And importantly, the fallback isn't a "fake PHP Poll API." It's an AsyncIt poller implementation with weaker/different characteristics.

But I would not start with stream_select() blindly

This is where the Poll API research becomes important.

We should compare the actual RFC semantics against what stream_select() can express.

For example:

Concern	Native Poll API	stream_select() fallback
Read readiness	?	✓
Write readiness	?	✓
Error readiness	?	✓
Multiple descriptors	?	✓
Timeout	?	✓
Interrupt/wakeup	?	limited
Edge-triggering	?	?
One-shot polling	?	?
OS-specific behavior	?	abstracted
File descriptors beyond PHP streams	?	limited
Future poller features	?	no

The exact cells need to come from the RFC/current implementation, not assumptions.

That's why I would make this part of the research rather than prematurely implementing it.

OCI is probably the sweet spot

For your actual development environment, I'd seriously consider an AsyncIt test matrix like:

Host
PHP 8.5 + Ondřej packages
│
├── normal AsyncIt development
│
└── OCI test environment
│
├── PHP 8.5
│     fallback poller
│
└── PHP 8.6-dev
native Poll API

Then CI can do something similar.

You don't need to replace your workstation PHP.

You simply get a specialized runtime laboratory for the new primitive.

That also fits your general infrastructure philosophy extremely well: don't pollute the host environment just because an experimental runtime feature needs a different toolchain.

And there is an even nicer possibility

Because AsyncIt will eventually have conformance tests, the Poll API doesn't need to be part of every test environment.

We could have:

AsyncIt Conformance
│
├── Generic Runtime Tests
│
├── Poller Contract
│      │
│      ├── SelectPoller
│      └── NativePoller
│
└── Runtime-specific Tests

So right now:

PHP 8.5
│
└── SelectPoller
↓
AsyncIt

Later:

PHP 8.6
│
└── NativePoller
↓
AsyncIt

The AsyncIt semantics don't change.

Only the lowest adapter does.

One caveat: Linux-only is actually a useful constraint

For the development polyfill, Linux-only is perfectly reasonable.

It could deliberately say:

SelectPoller is a development/reference implementation for environments without the native Poll API.

And later AsyncIt could have:

Linux
├── NativePoller
└── SelectPoller

Windows
└── NativePoller / other backend

macOS
└── NativePoller / other backend

I would not make the compatibility layer pretend to be cross-platform before we know what the native API actually guarantees.

What I'd recommend right now

Don't implement anything yet.

Instead, let's add one small section to the research deliverable:

"Pre-PHP-8.6 Poll API Strategy"

with four investigated options:

A. Userland compatibility implementation

What can actually be emulated?

B. PHP extension/backport

Could the RFC implementation itself be built as an extension against PHP 8.5, or is it intrinsically tied to 8.6 internals?

This is worth investigating specifically. If the Poll API implementation is separable as an extension, that could be much nicer than a whole custom PHP build.

C. Custom PHP build

Determine how difficult it is to build the RFC implementation against the current PHP development branch.

D. OCI development runtime

Determine the cleanest container setup for:

PHP source
+ Poll API implementation
+ AsyncIt
+ test/conformance suite

Then we can choose based on engineering friction, rather than guessing.

My current bet

I'd expect the eventual AsyncIt development setup to look something like:

                   AsyncIt
                      │
                 PollerPort
                /           \
               /             \
      SelectPoller        NativePoller
          │                    │
      PHP 8.5              PHP 8.6+
      Ondřej              custom/official
          │                    │
          └──────────┬─────────┘
                     │
               same conformance
                   suite

And the OCI PHP-8.6 environment becomes the experimental reference runtime until 8.6 is actually released.

That gives us something quite valuable: we can start developing AsyncIt's architecture against the future primitive without making the entire development environment depend on an unreleased PHP build.
