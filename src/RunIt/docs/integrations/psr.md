# PSR interoperability

RunIt models execution with its own vendor-neutral vocabulary (execution, activity,
observation, occurrence, snapshot). The adapters in `src/Adapter/Psr/` translate that
vocabulary to and from the PHP-FIG PSR interfaces so RunIt slots into existing
PSR-3/PSR-14/PSR-15/PSR-18/PSR-20 ecosystems without coupling the core to them.

## Required optional packages

The PSR adapters live in the same package but only declare dependencies on PSR
interface packages, which are listed under `require-dev` in `composer.json`:

| PSR | Package | Used by |
|-----|---------|---------|
| PSR-20 | `psr/clock` | `Adapter\Psr\Clock\Psr20Clock` |
| PSR-3 | `psr/log` | observation publisher, execution-aware decorator, observation bridge |
| PSR-14 | `psr/event-dispatcher` | occurrence publisher and event wrapper |
| PSR-7 | `psr/http-message` | request extractor, response enricher, client decorator |
| PSR-15 | `psr/http-server-middleware`, `psr/http-server-handler` | execution middleware |
| PSR-18 | `psr/http-client` | propagation-aware client decorator |

Concrete PSR implementations (for example a PSR-3 logger, a PSR-14 dispatcher, a
PSR-7/PSR-15/PSR-18 HTTP stack) are provided by the consuming application, not by
RunIt. PHP lazy-loads classes, so an adapter only fails if it is actually
instantiated; the rest of the package works whether or not the PSR packages are
present.

## PSR-20 clock

`Adapter\Psr\Clock\Psr20Clock` implements `MonotonicClockInterface`. It keeps the two
time sources separate, exactly as the contract requires:

- wall-clock timestamps come from the injected `Psr\Clock\ClockInterface::now()`;
- monotonic time (used only for measuring elapsed durations) comes from `hrtime()`
  and is never mixed with the wall clock.

A PSR-20 clock cannot supply a monotonic source, so durations always use the system
monotonic clock while timestamps honour the injected clock. This lets a test or
framework supply a fixed or virtual wall clock without corrupting duration
measurements.

```php
use ItPack\RunIt\Adapter\Psr\Clock\Psr20Clock;
use Psr\Clock\ClockInterface;

$clock = new Psr20Clock($somePsr20Clock); // ClockInterface
```

## PSR-3 observation export

`Adapter\Psr\Log\ObservationLoggerPublisher` implements `ArtifactPublisherInterface`
and maps a RunIt observation to a PSR-3 log record:

- stable message template → log message;
- severity → PSR-3 level via `ObservationToPsr3LevelMapperInterface`;
- attributes → logger context;
- safe throwable description → configurable context key (default `exception`);
- execution, activity, observation, diagnostic-code, sequence, timestamp and
  severity correlation fields are added to the context.

Redaction is applied before logging when a `RedactorInterface` is supplied; when the
publisher is used as the sink of a RunIt publication pipeline, the pipeline already
redacts upstream and hands a redacted artifact. The original artifact is never
mutated.

### Level mapping

RunIt severities mirror the PSR-3 levels and add a finer `trace` tier below
`debug`. `DefaultObservationToPsr3LevelMapper` folds `trace` into `debug` and maps
the rest by numeric threshold:

| RunIt severity | Numeric | PSR-3 level |
|----------------|---------|-------------|
| emergency | 600 | emergency |
| alert | 550 | alert |
| critical | 500 | critical |
| error | 400 | error |
| warning | 300 | warning |
| notice | 250 | notice |
| info | 200 | info |
| debug | 100 | debug |
| trace | 0 | debug |

Supply a custom `ObservationToPsr3LevelMapperInterface` to the publisher to override
the default mapping.

```php
use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;

$publisher = new ObservationLoggerPublisher($psr3Logger);
```

## PSR-3 execution-aware decorator

`Adapter\Psr\Log\ExecutionAwareLogger` (extends `Psr\Log\AbstractLogger`) decorates
any PSR-3 logger so every log line is enriched with the active RunIt execution (and,
when an activity is active, activity) correlation. When no execution is active the
call passes through unchanged.

Collision behaviour is governed by `LoggerCollisionPolicy`:

- `PreserveCaller` (default) — keep the caller's value and add the RunIt correlation
  under a `runit.` namespaced key;
- `Overwrite` — replace the caller-supplied correlation value.

```php
use ItPack\RunIt\Adapter\Psr\Log\ExecutionAwareLogger;
use ItPack\RunIt\Adapter\Psr\Log\LoggerCollisionPolicy;

$logger = new ExecutionAwareLogger(
    $psr3Logger,
    $currentExecution,
    LoggerCollisionPolicy::PreserveCaller,
);
```

## PSR-3 to observation bridge

`Adapter\Psr\Log\ObservationRecordingLogger` (extends `Psr\Log\AbstractLogger`)
records every log call as a RunIt observation through an
`ObservationRecorderInterface`:

- PSR-3 level → RunIt severity (folding unknown/empty levels to debug);
- message kept as the stable template;
- context becomes observation attributes; the `exception` context value (when a
  `Throwable`) is mapped through the recorder's throwable policy;
- non-scalar context values are dropped so observations never carry objects or
  resources.

A publication guard prevents the log-to-observation-to-log recursion: when
publication is already active on the current path the bridge returns without
recording. Pass a `PublicationGuardInterface` to enable the guard; otherwise the
bridge records every call.

```php
use ItPack\RunIt\Adapter\Psr\Log\ObservationRecordingLogger;

$logger = new ObservationRecordingLogger($recorder, $publicationGuard);
```

## PSR-14 occurrence bridge

The occurrence bridge publishes RunIt occurrences through a PSR-14
`EventDispatcherInterface`:

- `Adapter\Psr\Event\OccurrenceEvent` wraps an occurrence in a `StoppableEventInterface`.
  The occurrence itself stays immutable; only the propagation-stopped flag is
  mutable, so listeners may stop propagation without altering the occurrence.
- `Adapter\Psr\Event\OccurrencePublisher` (implements `ArtifactPublisherInterface`)
  wraps each occurrence and dispatches it. Non-occurrence artifacts are ignored.
- `Adapter\Psr\Event\DefaultPsrEventToOccurrenceMapper` maps an inbound PSR-14
  event object to a RunIt occurrence, binding it to a configured execution identity,
  minting a fresh occurrence identifier, and deriving the payload from the event
  (by default its public scalar/array properties, or a configurable data extractor).

### Failure behaviour

If dispatch throws, the configured `PublicationFailurePolicyInterface` is applied
(for example fail-open to swallow, or fail-closed to rethrow). When no policy is
configured the publisher rethrows so the caller observes the dispatcher failure; in
a RunIt publication pipeline the pipeline already wraps the publisher in a fail-safe
guard. Dispatch is synchronous; listener ordering and stoppable-event behaviour
follow whatever the dispatcher guarantees.

```php
use ItPack\RunIt\Adapter\Psr\Event\OccurrencePublisher;

$publisher = new OccurrencePublisher($psr14Dispatcher, $failurePolicy);
```

## PSR-15 middleware

`Adapter\Psr\Http\ExecutionMiddleware` (implements `Psr\Http\Server\MiddlewareInterface`)
runs each HTTP request inside a RunIt execution:

1. derive an execution definition from the request via
   `HttpContextExtractorInterface` (default `Psr7RequestContextExtractor`);
2. run the handler inside that execution through `ExecutionRunnerInterface`;
3. record the response status as an observation — `http.response.server_error`
   (Error) for 5xx, `http.response.client_error` (Warning) for 4xx;
4. on a handler `Throwable`, record `http.request.failed` (Error) and rethrow the
   original exception;
5. enrich the response with correlation headers via
   `ResponseCorrelationEnricherInterface` (default `Psr7ResponseCorrelationEnricher`);
6. the runner restores the prior context afterwards, so context is restored on every
   path (success, client error, server error, and thrown failure).

The request extractor derives a cardinality-safe execution name `METHOD /normalized-path`
where numeric and UUID-like path segments are replaced with `id`, so request URLs
never become a high-cardinality execution-name dimension. The correlation identifier
is read from a configurable header (default `X-Correlation-Id`) and carried in the
initial execution context.

```php
use ItPack\RunIt\Adapter\Psr\Http\ExecutionMiddleware;

$middleware = new ExecutionMiddleware($runner);
```

## PSR-18 propagation client

`Adapter\Psr\Http\Propagation\Psr18CorrelationClientDecorator` (implements
`Psr\Http\Client\ClientInterface`) injects RunIt correlation headers into every
outbound request. It reads the active execution (and correlation identifier) from the
ambient `CurrentExecutionInterface` and adds the headers produced by a
`HeaderPropagatorInterface` (default `DefaultHeaderPropagator`). When no execution is
active the request passes through unchanged. Requests are immutable, so a modified
copy is sent and the original request is never altered.

The propagator only emits configured header *names* with a validated execution id and
(correlation) value, so it never emits attacker-controlled header names.

```php
use ItPack\RunIt\Adapter\Psr\Http\Propagation\Psr18CorrelationClientDecorator;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\DefaultHeaderPropagator;

$client = new Psr18CorrelationClientDecorator(
    $psr18Client,
    new DefaultHeaderPropagator(),
    $currentExecution,
);
```

## HTTP server example

```php
use ItPack\RunIt\Adapter\Psr\Http\ExecutionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$runner = /* an ExecutionRunnerInterface from your wiring */;

$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // application code runs with an ambient execution + execution-aware logger
        return /* a PSR-7 ResponseInterface */;
    }
};

$middleware = new ExecutionMiddleware($runner);

$response = $middleware->process($request, $handler);
// $response now carries X-Execution-Id (and X-Correlation-Id when present)
```

## Outbound HTTP client example

```php
use ItPack\RunIt\Adapter\Psr\Http\Propagation\DefaultHeaderPropagator;
use ItPack\RunIt\Adapter\Psr\Http\Propagation\Psr18CorrelationClientDecorator;

$client = new Psr18CorrelationClientDecorator(
    $psr18Client,            // Psr\Http\Client\ClientInterface
    new DefaultHeaderPropagator(),
    $currentExecution,       // CurrentExecutionInterface from your wiring
);

// Inside an active execution, every sendRequest adds X-Execution-Id
// (and X-Correlation-Id when a correlation id is in context).
$response = $client->sendRequest($request);
```

## Behaviour summary

- Adapters translate RunIt concepts; they never change their meaning.
- Redaction runs before any external publication.
- Logger and event-dispatcher failures follow the configured publication policy
  (fail-open default through the pipeline guard; explicit rethrow when none is set).
- HTTP middleware restores context on every path.
- Occurrences remain immutable across PSR-14 wrappers; only propagation stopping is
  permitted.
