<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Sink;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sink that delivers a CloudEvent over HTTP via a PSR-18 client.
 *
 * The sink serializes the event to the structured JSON representation and posts
 * it to the configured target URI with the `application/cloudevents+json`
 * content type. It does not retry, does not buffer, and does not add any durable
 * delivery guarantee: a failed request throws and is the caller's responsibility
 * to handle under the configured publication failure policy. The request is
 * built immutably and the original CloudEvent is never altered.
 *
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
final class Psr18CloudEventSink implements CloudEventSinkInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly CloudEventSerializerInterface $serializer,
        private readonly string $targetUri,
        private readonly string $method = 'POST',
    ) {
    }

    public function send(CloudEvent $event): void
    {
        $body = $this->serializer->serialize($event);

        $request = $this->requestFactory
            ->createRequest($this->method, $this->targetUri)
            ->withHeader('Content-Type', DefaultCloudEventJsonSerializer::CONTENT_TYPE)
            ->withBody($this->streamFactory->createStream($body))
        ;

        $this->client->sendRequest($request);
    }
}
