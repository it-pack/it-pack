# CloudEvents integration

RunIt models execution with its own vendor-neutral vocabulary. The bridge in
`src/Adapter/CloudEvents/` translates RunIt **occurrences** to and from the
[CloudEvents](https://cloudevents.io) 1.0 envelope so RunIt facts can travel as
portable, spec-compliant events without coupling the core to any CloudEvents SDK.

The bridge is intentionally **SDK-free**: it implements the CloudEvents
structured JSON representation directly, so the core remains usable without
`cloudevents/sdk-php`. See
`docs/adr/ADR-021-cloudevents-bridge-placement.md`.

## Scope

The CloudEvents bridge maps **occurrences** only. Executions, activities,
measurements, and observations are not CloudEvents; they remain RunIt-internal
artifacts (and are exported through the OpenTelemetry and PSR bridges). The
`CloudEventPublisher` therefore publishes occurrences and ignores every other
artifact type, so it can sit inside the publication pipeline without side effects.

## Conceptual mapping

| RunIt occurrence field | CloudEvent field / extension |
|------------------------|------------------------------|
| `id` | `id` |
| `type` | `type` |
| `source` | `source` |
| `subject` | `subject` |
| `time` (epoch seconds) | `time` (RFC 3339, UTC) |
| `data` | `data` |
| `contentType` | `datacontenttype` |
| `dataSchema` | `dataschema` |
| `executionId` | extension `executionid` |
| `activityId` | extension `activityid` |
| `sequence` | extension `sequence` |
| `extensionAttributes['correlation.id']` | extension `correlationid` |
| `extensionAttributes[<valid>]` | extension `<valid>` |

CloudEvents extension attribute names must be lower-case letters, digits, and
optional hyphens. Custom RunIt occurrence extension attributes whose keys are
valid CloudEvents extension names are preserved; keys that cannot be represented
(such as dotted or upper-case RunIt attribute keys) are dropped, because the
spec cannot carry them. Unknown extensions on an inbound event are preserved in
the reconstructed occurrence's extension attributes, so round trips do not lose
information.

## Required optional packages

The bridge has **no required runtime dependency**. PSR-18 HTTP delivery, when
used, relies only on the PSR interfaces already available (`psr/http-client`,
`psr/http-factory`); a concrete HTTP client is supplied by the application.

| Capability | Package | Required |
|------------|---------|----------|
| Mapping, serialization, in-memory sinks | none | No |
| HTTP delivery | `psr/http-client` + `psr/http-factory` | Only for `Psr18CloudEventSink` |

`cloudevents/sdk-php` is listed under `suggest` for consumers who already use it,
but the bridge does not depend on it.

## Installation

```bash
# nothing required for mapping and serialization
composer require psr/http-client psr/http-factory
# add a concrete PSR-18 client when you want HTTP delivery, e.g.:
composer require symfony/http-client nyholm/psr7
```

## Mapping an occurrence to a CloudEvent

```php
use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;

$captured = [];
$publisher = CloudEventPublisher::fromConfig(
    CloudEventsBridgeConfig::builder()
        ->withSink(new CallbackCloudEventSink(static function ($event) use (&$captured): void {
            $captured[] = $event;
        }))
        ->build(),
);

$publisher->publish($occurrence);
```

## Serialization

The structured JSON serializer places the standard attributes and any extension
attributes as sibling keys:

```php
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;

$serializer = new DefaultCloudEventJsonSerializer();
$json = $serializer->serialize($event);   // application/cloudevents+json
$event = $serializer->deserialize($json); // reverse
```

Serialization enforces a maximum size (default 65 536 bytes) and rejects
oversized input, malformed JSON, and missing required fields.

## HTTP delivery

`Psr18CloudEventSink` posts the serialized event to a target URI with the
`application/cloudevents+json` content type. It performs a single best-effort
`POST`; it does **not** retry, buffer, or add durable delivery. A failed request
throws and is handled by the surrounding publication failure policy.

## Delivery guarantees

Delivery is **at-most-once** by default: the sink sends each event exactly once
and does not persist or retry it. Durable, exactly-once, or ordered delivery is
the responsibility of the transport the application wires in (for example a
broker with its own acknowledgement). The bridge never implies a stronger
guarantee.

## Event size limits

A single serialized CloudEvent must not exceed the configured maximum
(`CloudEventMappingPolicy::$maxSerializedBytes`). The bound keeps one event from
becoming an unbounded payload on the wire or in a buffer; oversized events fail
closed at serialization time.
