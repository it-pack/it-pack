<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-swoole-http-regressions.php
// This target-policy harness fails until recorder failures preserve handler outcomes.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Swoole\Http\ServerExecutionMiddleware;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\Psr\FakeExecutionScope;

$failures = [];
$check = /** Collect a failed assertion without stopping the remaining checks. */
    static function (bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures[] = $message;
        }
    };

$recorderError = new RuntimeException('synthetic recorder failure');
$handlerError = new RuntimeException('synthetic handler failure');
$recorder = new class ($recorderError) implements ObservationRecorderInterface {
    public int $calls = 0;

    /** @var list<array{string, ?ObservationSeverityInterface, ?AttributeCollectionInterface, ?Throwable, ?string}> */
    public array $arguments = [];

    /** Keep the error that the observation recorder will throw. */
    public function __construct(private readonly Throwable $error)
    {
    }

    /** Capture the observation arguments, then throw the configured error. */
    public function record(
        string $message,
        ?ObservationSeverityInterface $severity = null,
        ?AttributeCollectionInterface $attributes = null,
        ?Throwable $throwable = null,
        ?string $diagnosticCode = null,
    ): ObservationInterface {
        ++$this->calls;
        $this->arguments[] = [$message, $severity, $attributes, $throwable, $diagnosticCode];

        throw $this->error;
    }
};
$name = ExecutionName::fromString('http.request');
$kind = ExecutionKind::fromString('http.request');
$scope = new FakeExecutionScope(
    Execution::start(
        ExecutionId::fromString('swoole-target'),
        ExecutionDefinition::create($name, $kind),
        Instant::of(1),
    ),
    ExecutionContext::empty(),
    $recorder,
);
$runner = new readonly class ($scope) implements ExecutionRunnerInterface {
    /** Keep the scope passed to the operation. */
    public function __construct(private ExecutionScopeInterface $scope)
    {
    }

    /** Run the operation with the configured scope. */
    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        return $operation($this->scope);
    }
};
$middleware = new ServerExecutionMiddleware($runner, $name, $kind);
$request = new stdClass();
$response = new stdClass();
$handlerCalls = 0;
$result = null;
$caught = null;
try {
    $result = $middleware->handle($request, /** Count the call and return the response. */
        static function () use ($response, &$handlerCalls): object {
            ++$handlerCalls;

            return $response;
        });
} catch (Throwable $throwable) {
    $caught = $throwable;
}

$check($handlerCalls === 1, 'successful handler is invoked once');
$check($recorder->calls === 1, 'recorder is invoked for successful handler');
$check(!$caught instanceof Throwable && $result === $response, 'successful handler returns the exact response object');

$caught = null;
try {
    $middleware->handle($request, /** Count the call and throw the original handler error. */
        static function () use ($handlerError, &$handlerCalls): never {
            ++$handlerCalls;

            throw $handlerError;
        });
} catch (Throwable $throwable) {
    $caught = $throwable;
}

$check($handlerCalls === 2, 'failing handler is invoked once');
$check($recorder->calls === 2, 'recorder is invoked for failing handler');
$check(
    $recorder->arguments === array_fill(0, 2, ['HTTP request handled', null, null, null, 'http.request']),
    'recorder arguments are preserved',
);
$check($handlerError !== $recorderError, 'handler and recorder failures are distinct objects');
$check($caught === $handlerError, 'failing handler propagates the exact original exception');

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " Swoole HTTP target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "Swoole HTTP target-policy checks passed.\n");
