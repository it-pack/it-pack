<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise\Http;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Http\CurlHttpClient;
use ReflectionMethod;
use RuntimeException;

/**
 * Wire-policy coverage for the cURL transport: redirect following must stay
 * disabled (authenticated headers would otherwise be forwarded to redirect
 * targets) and timeouts must come from named, injectable constants (REVIEW NV4).
 *
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\Http\CurlHttpClient
 */
final class CurlHttpClientTest extends Unit
{
    private string $file;

    public function testRedirectFollowingIsDeliberatelyAbsent(): void
    {
        $options = $this->buildOptions('GET', ['Authorization' => 'Bearer x'], null);

        verify(isset($options[CURLOPT_FOLLOWLOCATION]))->false();
    }

    public function testDefaultOptionAssembly(): void
    {
        $options = $this->buildOptions('GET', ['Authorization' => 'Bearer x'], null);

        verify($options[CURLOPT_CUSTOMREQUEST])->equals('GET');
        verify($options[CURLOPT_HTTPHEADER])->equals(['Authorization: Bearer x']);
        verify($options[CURLOPT_RETURNTRANSFER])->true();
        verify($options[CURLOPT_TIMEOUT])->equals(CurlHttpClient::REQUEST_TIMEOUT_SECONDS);
        verify($options[CURLOPT_CONNECTTIMEOUT])->equals(CurlHttpClient::CONNECT_TIMEOUT_SECONDS);
        verify($options[CURLOPT_FAILONERROR])->false();
        verify(isset($options[CURLOPT_POSTFIELDS]))->false();
    }

    public function testBodyIsAttachedAndTimeoutsAreInjectable(): void
    {
        $client = new CurlHttpClient(timeoutSeconds: 3, connectTimeoutSeconds: 2);
        $method = new ReflectionMethod($client, 'buildOptions');

        $options = $method->invoke($client, 'POST', [], '{"a":1}');

        if (!is_array($options)) {
            self::fail('buildOptions must return an options array.');
        }

        verify($options[CURLOPT_POSTFIELDS])->equals('{"a":1}');
        verify($options[CURLOPT_TIMEOUT])->equals(3);
        verify($options[CURLOPT_CONNECTTIMEOUT])->equals(2);
    }

    public function testEmptyMethodIsRejectedBeforeAnyNetworkUse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not be empty');

        new CurlHttpClient()->request('', 'https://config.acme.test/app');
    }

    public function testMultipleHeadersAreJoinedWithColonSpace(): void
    {
        $options = $this->buildOptions('GET', ['Authorization' => 'Bearer x', 'X-Trace' => 'abc'], null);

        verify($options[CURLOPT_HTTPHEADER])->equals(['Authorization: Bearer x', 'X-Trace: abc']);
    }

    /**
     * @dataProvider httpMethods
     */
    public function testEveryMethodIsCarriedAsCustomRequest(string $method): void
    {
        $options = $this->buildOptions($method, [], null);

        verify($options[CURLOPT_CUSTOMREQUEST])->equals($method);
    }

    /**
     * @return array<string, array{string}>
     */
    public function httpMethods(): array
    {
        return [
            'GET' => ['GET'],
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'DELETE' => ['DELETE'],
            'PATCH' => ['PATCH'],
            'HEAD' => ['HEAD'],
        ];
    }

    public function testRequestAgainstLocalFileExercisesTheFullHappyPath(): void
    {
        file_put_contents($this->file, 'hello-world');

        $response = new CurlHttpClient()->request('GET', 'file://' . $this->file);

        self::assertInstanceOf(\ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse::class, $response);
        verify($response->body)->equals('hello-world');
        verify($response->isSuccess())->false();
    }

    public function testConnectionRefusedProducesFormattedFailure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#^HTTP "GET http://127\.0\.0\.1:1/" failed: #');

        new CurlHttpClient()->request('GET', 'http://127.0.0.1:1/');
    }

    protected function _before(): void
    {
        $this->file = sys_get_temp_dir() . '/cfg-curl-' . uniqid();
    }

    protected function _after(): void
    {
        if (isset($this->file) && is_file($this->file)) {
            @unlink($this->file);
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array<array-key, mixed>
     */
    private function buildOptions(string $upperMethod, array $headers, ?string $body): array
    {
        $client = new CurlHttpClient();
        $method = new ReflectionMethod($client, 'buildOptions');

        $options = $method->invoke($client, $upperMethod, $headers, $body);

        if (!is_array($options)) {
            self::fail('buildOptions must return an options array.');
        }

        return $options;
    }
}
