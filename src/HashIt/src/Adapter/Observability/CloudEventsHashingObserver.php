<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter\Observability;

use CloudEvents\Exceptions\UnsupportedSpecVersionException;
use CloudEvents\Serializers\JsonSerializer;
use CloudEvents\V1\CloudEventImmutable;
use CloudEvents\V1\CloudEventInterface;
use ItPack\HashIt\HashingObserverInterface;
use ItPack\HashIt\HashObservation;
use LogicException;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Random\RandomException;
use Throwable;

/**
 * CNCF CloudEvents emitter adapter for the core {@see HashingObserverInterface} port.
 *
 * On each `completed` / `failed` observation it builds a standards-shaped CloudEvent
 * (spec 1.0) carrying the hashing result or failure as structured JSON `data`, then
 * either keeps it for inspection or POSTs it to a broker through an injected PSR-18
 * client. `started` / `progress` observations are not event-worthy and are ignored.
 *
 * This adapter is *optional*: it is guarded so it can only be constructed when
 * `cloudevents/sdk-php` is installed. The composition root never instantiates it
 * automatically — a consumer injects it, so the CloudEvents dependency stays optional
 * in production. Delivery transport (the PSR-18 client) is likewise optional: without
 * it the emitted events are simply retained for inspection.
 */
final class CloudEventsHashingObserver implements HashingObserverInterface
{
    private const string SOURCE_TEMPLATE = 'hash-it://%s';

    private const string TYPE_COMPLETED = 'io.itpack.hashit.hash.completed.v1';

    private const string TYPE_FAILED = 'io.itpack.hashit.hash.failed.v1';

    /** @var list<CloudEventInterface> */
    private array $emitted = [];

    private string $source;

    private ?ClientInterface $client;

    private ?string $brokerUrl;

    public function __construct(
        ?string $source = null,
        ?ClientInterface $client = null,
        ?string $brokerUrl = null,
    ) {
        if (!class_exists(CloudEventImmutable::class)) {
            throw new LogicException(
                'CloudEvents adapter requires cloudevents/sdk-php. Install it to use ' . self::class,
            );
        }

        $this->source = $source ?? sprintf(self::SOURCE_TEMPLATE, (string) gethostname());
        $this->client = $client;
        $this->brokerUrl = $brokerUrl;
    }

    /**
     * @throws UnsupportedSpecVersionException
     * @throws ClientExceptionInterface
     * @throws RandomException
     */
    public function observe(HashObservation $observation): void
    {
        switch ($observation->kind) {
            case HashObservation::COMPLETED:
                $this->emit(self::TYPE_COMPLETED, [
                    'algorithm' => $observation->algorithm,
                    'bytes' => $observation->bytes,
                    'durationMs' => $observation->durationMs,
                    'urn' => $observation->resultUrn,
                ]);
                break;

            case HashObservation::FAILED:
                $error = $observation->error;
                $this->emit(self::TYPE_FAILED, [
                    'operation' => $observation->operation?->name,
                    'error' => $error instanceof Throwable ? $error->getMessage() : null,
                    'errorType' => $error instanceof Throwable ? $error::class : null,
                ]);
                break;
        }
    }

    /**
     * @return list<CloudEventInterface>
     */
    public function collected(): array
    {
        return $this->emitted;
    }

    /**
     * @param array<string, mixed> $data
     * @throws RandomException
     * @throws UnsupportedSpecVersionException
     * @throws ClientExceptionInterface
     */
    private function emit(string $type, array $data): void
    {
        $event = new CloudEventImmutable(bin2hex(random_bytes(16)), $this->source, $type)
            ->withDataContentType('application/json')
            ->withData($data)
        ;

        $this->emitted[] = $event;

        if ($this->client !== null && $this->brokerUrl !== null) {
            $request = new Request(
                'POST',
                $this->brokerUrl,
                ['Content-Type' => 'application/cloudevents+json'],
                JsonSerializer::create()->serializeStructured($event),
            );
            $this->client->sendRequest($request);
        }
    }
}
