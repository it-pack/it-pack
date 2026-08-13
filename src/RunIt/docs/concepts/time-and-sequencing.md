# Time and sequencing

RunIt measures points in time and elapsed time with two small immutable value
objects, orders artifacts with an immutable sequence, and obtains time through
a port that keeps wall-clock and monotonic time strictly separate. The design
decision is recorded in
[ADR-019](adr/ADR-019-monotonic-time-and-immutable-sequences.md).

## Instant

`ItPack\RunIt\Domain\Shared\Instant` is an immutable point in time expressed as
Unix epoch seconds. It carries no timezone; a local representation is derived
only at the edge (in a serializer or an adapter). Create it with
`Instant::of($epochSeconds)` or `Instant::now()`.

```php
use ItPack\RunIt\Domain\Shared\Instant;

$started = Instant::of(1_700_000_000);
$now = Instant::now();
```

A negative epoch is rejected by the constructor guard.

## Duration

`ItPack\RunIt\Domain\Shared\Duration` is a non-negative elapsed time. It is
represented internally as integer nanoseconds so sub-second precision is exact
and never rounds like a float would. Build it from any unit:

```php
use ItPack\RunIt\Domain\Shared\Duration;

Duration::ofSeconds(2);        // 2_000_000_000 ns
Duration::ofMilliseconds(500); // 500_000_000 ns
Duration::ofMicroseconds(1);   // 1_000 ns
Duration::ofNanoseconds(1);    // 1 ns
```

`nanoseconds()`, `microseconds()`, `milliseconds()`, and `seconds()` project the
value (truncating towards zero); `plus()` adds immutably; `equals()` compares by
value. Durations are always measured with monotonic time — never wall-clock time
— because wall-clock adjustments would corrupt an elapsed-time measurement.

## ArtifactSequence

`ItPack\RunIt\Domain\Shared\ArtifactSequence` numbers artifacts within one
execution so they can be ordered deterministically. It is a value: `next()`
returns a new sequence and never mutates the receiver.

```php
use ItPack\RunIt\Domain\Shared\ArtifactSequence;

$zero = ArtifactSequence::initial(); // value 0
$one = $zero->next();                // value 1; $zero is unchanged
```

## SequenceAllocator (port)

`SequenceAllocatorInterface` allocates the next sequence for an execution. The
first allocation for an execution returns the initial sequence (zero) and each
subsequent allocation returns the next value; sequences reset per execution.
Allocation is a port, so the storage strategy is an adapter concern:

- `ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator` keeps a per-execution
  counter in memory. It is correct within a single process but is not durable
  across restarts; durable allocators are separate adapters.

## MonotonicClock (port)

`MonotonicClockInterface` supplies time to the domain. Wall-clock seconds
(`nowSeconds()`) and monotonic nanoseconds (`monotonicNanoseconds()`) are
distinct methods, and `measure()` returns a `Duration` between two monotonic
readings:

- `ItPack\RunIt\Adapter\Common\SystemMonotonicClock` reads the wall clock via
  `time()` and monotonic time via `hrtime()`.
- Tests use `Tests\Support\Fixture\DeterministicMonotonicClock`, a deterministic
  double whose values are settable, so duration assertions need no sleeping.

## Vocabulary

- `runit:instant` — a point in time as Unix epoch seconds.
- `runit:duration` — duration in nanoseconds, measured with monotonic time.
- `runit:sequence` — the monotonic artifact sequence within an execution.

## Tests

- `tests/Unit/Domain/Shared/TimeTest.php` covers instant and duration construction,
  conversion, addition, and rejection of negative values.
- `tests/Unit/Adapter/Common/SequenceAllocatorTest.php` covers increasing,
  per-execution allocation.
- `tests/Unit/Adapter/Common/MonotonicClockTest.php` covers wall/monotonic
  separation, duration measurement, and the system clock.
