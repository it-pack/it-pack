<?php

declare(strict_types=1);

/**
 * RunIt CloudEvents bridge — executable example.
 *
 * Builds a RunIt occurrence, maps it to a CloudEvent through the bridge, and
 * serializes it to structured JSON. A callback sink captures the event; a PSR-18
 * sink is shown for HTTP delivery, wired against a no-op client so the example
 * runs without a network.
 *
 * Run from the package root: `php examples/cloudevents.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Adapter\CloudEvents\Sink\Psr18CloudEventSink;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$occurrence = Occurrence::create(
    OccurrenceId::fromString('occ-42'),
    ExecutionId::fromString('exec-7'),
    OccurrenceType::fromString('itpack.configuration.loaded.v1'),
    'it-pack/example',
    Instant::of(1_700_000_000),
    ['outcome' => 'ok', 'duration_ms' => 12],
    contentType: 'application/json',
    extensionAttributes: AttributeCollection::fromArray([
        'correlation.id' => 'corr-99',
        'tenantflag' => 'acme',
    ]),
);

$serializer = new DefaultCloudEventJsonSerializer();
$published = [];

$publisher = CloudEventPublisher::fromConfig(
    CloudEventsBridgeConfig::builder()
        ->withSink(new CallbackCloudEventSink(static function ($event) use (&$published, $serializer): void {
            $published[] = $serializer->serialize($event);
        }))
        ->build(),
);

try {
    $publisher->publish($occurrence);
} catch (Throwable) {

}

echo "Published CloudEvent(s):\n";
foreach ($published as $json) {
    echo $json . "\n";
}

// HTTP delivery wiring (no-op client, so the example stays offline):
$noOpClient = new class () implements ClientInterface {
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        echo "\nWould POST to {$request->getUri()} with body: {$request->getBody()}\n";

        return new Response(204);
    }
};

$factory = new Psr17Factory();
$httpPublisher = CloudEventPublisher::fromConfig(
    CloudEventsBridgeConfig::builder()
        ->withSink(new Psr18CloudEventSink(
            $noOpClient,
            $factory,
            $factory,
            $serializer,
            'https://events.example/incoming',
        ))
        ->build(),
);

try {
    $httpPublisher->publish($occurrence);
} catch (Throwable) {

}
