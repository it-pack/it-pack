# Throwable normalization

RunIt records failures as immutable, serializable descriptions rather than live
exception objects. A [`ThrowableDescription`](https://w3id.org/itpack/runit#ThrowableDescription)
captures only structural, bounded facts about a thrown error or exception, so
artifacts can describe what failed without retaining the original `Throwable`,
a stack frame, or an unbounded payload. The safe mapping is performed by a
[`ThrowableMapperInterface`](https://w3id.org/itpack/runit#ThrowableDescription)
implementation such as `DefaultThrowableMapper`.

## Types

| Contract | Value object / service | Notes |
|---|---|---|
| `ThrowableDescriptionInterface` | `ItPack\RunIt\Domain\Shared\ThrowableDescription` | Immutable description |
| `ThrowableMapperInterface` | `ItPack\RunIt\Domain\Shared\DefaultThrowableMapper` | Safe default mapper |

## Captured fields

A description carries five fields:

- `class` (`string`) — the throwable class name.
- `message` (`string`) — the message, truncated to the configured maximum
  length.
- `code` (`int`) — `Throwable::getCode()`, which is typed `int` and therefore
  always serializable.
- `previous` (`ThrowableDescriptionInterface|null`) — the safe previous cause,
  captured recursively up to the configured depth.
- `trace` (`string|null`) — the stack trace, included only when the mapper's
  trace policy enables it and then truncated to the configured maximum length.

## Safety policy

Capture is policy-driven, never a guess:

- **Message length** is bounded (`DefaultThrowableMapper::DEFAULT_MAX_MESSAGE_LENGTH`
  = 1024 bytes). A negative bound disables truncation for callers that
  intentionally permit wider capture.
- **Stack-trace inclusion** is off by default. Enabling it is an explicit
  policy decision because traces can carry path and argument information.
- **Trace length** is bounded (`DEFAULT_MAX_TRACE_LENGTH` = 8192 bytes).
- **Cause depth** is bounded (`DEFAULT_MAX_CAUSE_DEPTH` = 5) so a deeply
  nested chain cannot produce an unbounded description.

The description holds no reference to the original `Throwable`: it is assembled
from captured primitives and nested descriptions, so immutable artifacts are
safe to serialize, redact, and export.

## Usage

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;

$mapper = new DefaultThrowableMapper(includeTrace: true);
$description = $mapper->describe($throwable);

$description->class();   // e.g. RuntimeException
$description->message(); // bounded
$description->code();    // int
$description->previous(); // ThrowableDescriptionInterface|null
$description->toArray();  // deterministic, recursively-primitive map
```

A description produced with tracing disabled omits the trace entirely, and a
cause chain deeper than the configured depth stops at the limit rather than
growing without bound.
