# CloudEvents integration

RunIt models execution with its own vendor-neutral vocabulary. The bridge in
`src/Adapter/CloudEvents/` translates RunIt **occurrences** to and from the
[CloudEvents](https://cloudevents.io) 1.0 envelope so RunIt facts can travel as
portable events without coupling domain or application code to the CloudEvents SDK.

The package directly requires `cloudevents/sdk-php` for structured JSON handling,
even when the bridge is not used. See
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

Occurrence mapping has its own extension filtering and reserved fields. Dotted or
upper-case RunIt attribute keys cannot be mapped as ordinary CloudEvent extensions;
`correlation.id` is mapped separately. Strict mapping rejects incompatible names and
collisions; legacy mapping can discard or overwrite attributes. Do not assume
arbitrary occurrence attributes survive mapping. Strict SDK serialization rejects events containing
unrepresentable extensions rather than silently rewriting them. For example, legacy
hyphenated names, reserved or colliding names, and array, float or null extension
values require particular migration attention. Test producer and consumer fixtures.

## Dependencies

The CloudEvents SDK is a direct RunIt runtime dependency. For PSR-18 HTTP
delivery, supply the PSR interfaces, factories, and a concrete client.

| Capability | Package | Required |
|------------|---------|----------|
| Mapping, serialization, in-memory sinks | `cloudevents/sdk-php` `^1.2` | Installed with RunIt |
| HTTP delivery | `psr/http-client` + `psr/http-factory` | Only for `Psr18CloudEventSink` |

## Installation

```bash
# The CloudEvents SDK is installed with RunIt.
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

The default is strict SDK validation. Incompatible versions, fields or extension
names and values fail with `InvalidCloudEventException`; wire formatting may also
differ from the legacy serializer. An explicit transitional legacy fallback can
use the entire legacy path for an incompatible event, but its output may not
conform to CloudEvents. The legacy facade and mapper have their own lossiness
limitations, including collisions and filtered attributes; fallback cannot make
data that those components cannot represent lossless. Fallback is not a bypass
for malformed JSON, size and depth limits, transport errors, or unrelated failures.
It cannot restore fields already filtered during occurrence mapping.

Mapping policy and an independently constructed serializer are configured
separately: configuring the mapper does not configure the serializer or a custom
sink. For example, to opt in on both components:

```php
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;

$policy = new CloudEventMappingPolicy(allowLegacyFallback: true);
$serializer = new DefaultCloudEventJsonSerializer($policy);
$config = CloudEventsBridgeConfig::builder()
    ->withPolicy($policy)
    ->withSink($sink) // Configure the sink to use $serializer if it serializes.
    ->build();
```

The SDK may normalize timestamps; strict processing rejects changes it cannot
preserve. Explicit null data and `data_base64` are not fully representable by the
RunIt facade, including on fallback. Before upgrading, exercise real legacy fixtures through both encoding and
decoding, update incompatible producers and consumers, and use fallback only during
the transition. Review custom mappers and serializers as well as wire consumers.

## HTTP delivery

`Psr18CloudEventSink` posts the serialized event to a target URI with the
`application/cloudevents+json` content type. It performs a single best-effort
`POST`; it does **not** retry, buffer, or add durable delivery. A failed request
client exception propagates to the surrounding publication failure policy. HTTP
response status codes, including 4xx and 5xx, are not treated as failures by the sink.

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
