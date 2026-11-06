<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole\Http;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\Http\ServerExecutionMiddleware;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M11.6 — server request middleware wraps each request in one execution.
 */
final class ServerExecutionMiddlewareTest extends Unit
{
    private function middleware(RunItTestKit $kit): ServerExecutionMiddleware
    {
        return new ServerExecutionMiddleware(
            $kit->runner,
            ExecutionName::fromString('http.request'),
            ExecutionKind::fromString('http.request'),
            clock: $kit->clock,
        );
    }

    public function testWrapsHandlerInOneExecutionAndReturnsResponse(): void
    {
        $kit = new RunItTestKit();
        $middleware = $this->middleware($kit);
        $response = new class () {
            public function getStatusCode(): int
            {
                return 201;
            }
        };

        $result = $middleware->handle(new stdClass(), static fn (mixed $request, $scope): object => $response);

        self::assertSame($response, $result);

        $handledObservation = false;
        $durationRecorded = false;
        $executionIds = [];

        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof ExecutionInterface) {
                $executionIds[$artifact->id()->toString()] = true;
            }

            if ($artifact instanceof ObservationInterface && $artifact->diagnosticCode() === 'http.request') {
                $handledObservation = true;
            }

            if ($artifact instanceof MeasurementInterface
                && $artifact->name()->toString() === 'http.request.duration'
            ) {
                $durationRecorded = true;
            }
        }

        self::assertCount(1, $executionIds, 'a single request must own a single execution identity');
        self::assertTrue($handledObservation, 'expected an http.request observation');
        self::assertTrue($durationRecorded, 'expected a request duration measurement');
    }

    public function testUnhandledThrowablePropagates(): void
    {
        $kit = new RunItTestKit();
        $middleware = $this->middleware($kit);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('request failed');

        $middleware->handle(new stdClass(), static function (): never {
            throw new RuntimeException('request failed');
        });
    }
}
