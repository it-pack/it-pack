<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Observability;

use CloudEvents\Serializers\JsonDeserializer;
use CloudEvents\Serializers\JsonSerializer;
use CloudEvents\V1\CloudEventInterface;
use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\Observability\CloudEventsHashingObserver;
use ItPack\HashIt\HashObservation;
use ItPack\HashIt\HashOperation;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class CloudEventsHashingObserverTest extends Unit
{
    public function testCompletedObservationYieldsCompletedCloudEvent(): void
    {
        $observer = new CloudEventsHashingObserver();

        $observer->observe(
            HashObservation::completed('op1', 'sha256', 42, 7, 'urn:hashit:sha256:deadbeef'),
        );

        self::assertCount(1, $observer->collected());
        $event = $observer->collected()[0];

        self::assertInstanceOf(CloudEventInterface::class, $event);
        self::assertSame('io.itpack.hashit.hash.completed.v1', $event->getType());
        self::assertSame('application/json', $event->getDataContentType());
        self::assertStringStartsWith('hash-it://', $event->getSource());
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $event->getId());

        /** @var array<string, mixed> $data */
        $data = $event->getData();
        self::assertSame('sha256', $data['algorithm']);
        self::assertSame(42, $data['bytes']);
        self::assertSame(7, $data['durationMs']);
        self::assertSame('urn:hashit:sha256:deadbeef', $data['urn']);
    }

    public function testFailedObservationYieldsFailedCloudEventWithError(): void
    {
        $observer = new CloudEventsHashingObserver();
        $error = new RuntimeException('boom');

        $observer->observe(HashObservation::failed('op2', HashOperation::HMAC_FILE, $error));

        self::assertCount(1, $observer->collected());
        $event = $observer->collected()[0];

        self::assertSame('io.itpack.hashit.hash.failed.v1', $event->getType());

        /** @var array<string, mixed> $data */
        $data = $event->getData();
        self::assertSame('HMAC_FILE', $data['operation']);
        self::assertSame('boom', $data['error']);
        self::assertSame(RuntimeException::class, $data['errorType']);
    }

    public function testStartedAndProgressAreIgnored(): void
    {
        $observer = new CloudEventsHashingObserver();

        $observer->observe(HashObservation::started(HashOperation::HASH, 'op3', 'sha256', 10));
        $observer->observe(HashObservation::progress('op3', 5, 10));

        self::assertCount(0, $observer->collected());
    }

    public function testEventRoundTripsThroughJsonSerializerAndDeserializer(): void
    {
        $observer = new CloudEventsHashingObserver();
        $observer->observe(
            HashObservation::completed('op4', 'sha512', 100, 3, 'urn:hashit:sha512:cafe'),
        );

        $json = JsonSerializer::create()->serializeStructured($observer->collected()[0]);
        $restored = JsonDeserializer::create()->deserializeStructured($json);
        self::assertInstanceOf(CloudEventInterface::class, $restored);

        self::assertSame('io.itpack.hashit.hash.completed.v1', $restored->getType());
        self::assertSame('hash-it://' . gethostname(), $restored->getSource());

        /** @var array<string, mixed> $data */
        $data = $restored->getData();
        self::assertSame('sha512', $data['algorithm']);
        self::assertSame('urn:hashit:sha512:cafe', $data['urn']);
    }

    public function testConfigurableSourceIsUsed(): void
    {
        $observer = new CloudEventsHashingObserver('https://example.test/hash-it');

        $observer->observe(HashObservation::completed('op5', 'sha256', 1, 1, null));

        self::assertSame('https://example.test/hash-it', $observer->collected()[0]->getSource());
    }

    public function testTransportPostsSerializedEventToBroker(): void
    {
        $client = new SpyClient();
        $observer = new CloudEventsHashingObserver(
            source: 'hash-it://host',
            client: $client,
            brokerUrl: 'https://broker.test/ce',
        );

        $observer->observe(HashObservation::completed('op6', 'sha256', 9, 2, 'urn:hashit:sha256:feed'));

        self::assertCount(1, $client->requests);
        self::assertSame('POST', $client->requests[0]->getMethod());
        self::assertSame('https://broker.test/ce', (string) $client->requests[0]->getUri());
        self::assertStringContainsString(
            'io.itpack.hashit.hash.completed.v1',
            (string) $client->requests[0]->getBody(),
        );
    }

    public function testWiredIntoCompositionRoot(): void
    {
        $observer = new CloudEventsHashingObserver();

        $hash = NativeHashIt::create($observer)->hash('observe me', 'sha256');

        self::assertCount(1, $observer->collected());
        self::assertSame('io.itpack.hashit.hash.completed.v1', $observer->collected()[0]->getType());

        /** @var array<string, mixed> $data */
        $data = $observer->collected()[0]->getData();
        self::assertSame($hash->toUrn(), $data['urn']);
    }
}

final class SpyClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new class () implements ResponseInterface {
            public function getProtocolVersion(): string
            {
                return '1.1';
            }
            public function withProtocolVersion(string $version): self
            {
                return $this;
            }
            public function getHeaders(): array
            {
                return [];
            }
            public function hasHeader(string $name): bool
            {
                return false;
            }
            public function getHeader(string $name): array
            {
                return [];
            }
            public function getHeaderLine(string $name): string
            {
                return '';
            }
            public function withHeader(string $name, $value): self
            {
                return $this;
            }
            public function withAddedHeader(string $name, $value): self
            {
                return $this;
            }
            public function withoutHeader(string $name): self
            {
                return $this;
            }
            public function getBody(): \Psr\Http\Message\StreamInterface
            {
                return new class () implements \Psr\Http\Message\StreamInterface {
                    public function __toString(): string
                    {
                        return '';
                    }
                    public function close(): void
                    {
                    }
                    public function detach()
                    {
                        return null;
                    }
                    public function getSize(): int
                    {
                        return 0;
                    }
                    public function tell(): int
                    {
                        return 0;
                    }
                    public function eof(): bool
                    {
                        return true;
                    }
                    public function isSeekable(): bool
                    {
                        return false;
                    }
                    public function seek(int $offset, int $whence = SEEK_SET): void
                    {
                    }
                    public function rewind(): void
                    {
                    }
                    public function isWritable(): bool
                    {
                        return false;
                    }
                    public function write(string $string): int
                    {
                        return 0;
                    }
                    public function isReadable(): bool
                    {
                        return false;
                    }
                    public function read(int $length): string
                    {
                        return '';
                    }
                    public function getContents(): string
                    {
                        return '';
                    }
                    public function getMetadata(?string $key = null)
                    {
                        return null;
                    }
                };
            }
            public function getStatusCode(): int
            {
                return 204;
            }
            public function withStatus(int $code, string $reasonPhrase = ''): self
            {
                return $this;
            }
            public function getReasonPhrase(): string
            {
                return '';
            }
            public function withBody(\Psr\Http\Message\StreamInterface $body): self
            {
                return $this;
            }
        };
    }
}
