<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Adapter\CloudEvents\Sink\Psr18CloudEventSink;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tests\Support\Fixture\Psr\RecordingHttpClient;

/**
 * M10.5 — delivery sinks (callback and PSR-18 HTTP) and failure behavior.
 */
final class CloudEventSinkTest extends Unit
{
    public function testCallbackSinkReceivesEvent(): void
    {
        $received = null;
        $sink = new CallbackCloudEventSink(static function (CloudEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = CloudEvent::create('occ-1', '/source', 'com.example.thing.happened.v1');
        $sink->send($event);

        self::assertSame($event, $received);
    }

    public function testPsr18SinkPostsJsonWithContentType(): void
    {
        $client = new RecordingHttpClient(new Response(200));
        $factory = new Psr17Factory();

        $sink = new Psr18CloudEventSink(
            $client,
            $factory,
            $factory,
            new DefaultCloudEventJsonSerializer(),
            'https://events.example/incoming',
        );

        $event = CloudEvent::create(
            'occ-1',
            '/source',
            'com.example.thing.happened.v1',
            ['outcome' => 'ok'],
            time: '2026-08-20T14:05:47Z',
        );
        $sink->send($event);

        $headers = $client->lastHeaders();
        self::assertSame(DefaultCloudEventJsonSerializer::CONTENT_TYPE, $headers['Content-Type']);
        self::assertStringContainsString('"id":"occ-1"', $client->lastBody());
    }

    public function testPsr18SinkPropagatesDeliveryFailure(): void
    {
        $failing = new class () implements ClientInterface {
            public function sendRequest(
                RequestInterface $request,
            ): ResponseInterface {
                throw new RuntimeException('downstream unavailable');
            }
        };

        $factory = new Psr17Factory();
        $sink = new Psr18CloudEventSink(
            $failing,
            $factory,
            $factory,
            new DefaultCloudEventJsonSerializer(),
            'https://events.example/incoming',
        );

        $event = CloudEvent::create('occ-1', '/source', 'com.example.thing.happened.v1');

        $this->expectException(RuntimeException::class);
        $sink->send($event);
    }
}
