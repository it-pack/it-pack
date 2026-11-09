<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-psr15-regressions.php
// This target-policy control fails until recorder failures preserve handler exceptions.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Psr\Http\ExecutionMiddleware;
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
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\Instant;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\Support\Fixture\Psr\FakeExecutionScope;

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$handlerError = new RuntimeException('synthetic handler failure');
$recorderError = new RuntimeException('synthetic recorder failure');
$recorder = new class ($recorderError) implements ObservationRecorderInterface {
    public int $calls = 0;

    /** @var list<array{string, ?ObservationSeverityInterface, ?AttributeCollectionInterface, ?Throwable, ?string}> */
    public array $arguments = [];

    public function __construct(private readonly Throwable $error)
    {
    }

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
$scope = new FakeExecutionScope(
    Execution::start(
        ExecutionId::fromString('psr15-target'),
        ExecutionDefinition::create(ExecutionName::fromString('http.request'), ExecutionKind::fromString('http')),
        Instant::of(1),
    ),
    ExecutionContext::empty(),
    $recorder,
);
$runner = new readonly class ($scope) implements ExecutionRunnerInterface {
    public function __construct(private ExecutionScopeInterface $scope)
    {
    }

    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        return $operation($this->scope);
    }
};
$handler = new class ($handlerError) implements RequestHandlerInterface {
    public int $calls = 0;

    public function __construct(private readonly Throwable $error)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;

        throw $this->error;
    }
};
$caught = null;
$middleware = new ExecutionMiddleware($runner);
try {
    $middleware->process(new ServerRequest('GET', '/failure'), $handler);
} catch (Throwable $throwable) {
    $caught = $throwable;
}

$check($handler->calls === 1, 'handler is invoked once');
$check($recorder->calls === 1, 'recorder is invoked once');
$arguments = $recorder->arguments[0] ?? null;
$check($arguments !== null && $arguments[0] === 'http.request.failed', 'failure message is recorded');
$check($arguments !== null && $arguments[1] === ObservationSeverity::Error, 'error severity is recorded');
$check(
    $arguments !== null && $arguments[2]?->toArray() === ['error' => 'synthetic handler failure'],
    'handler error attributes are recorded',
);
$check($arguments !== null && $arguments[3] === $handlerError, 'recorder receives the original handler exception');
$check($arguments !== null && $arguments[4] === null, 'diagnostic code is absent');
$check($caught === $handlerError, 'the exact original handler exception is propagated');

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " PSR-15 target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "PSR-15 target-policy checks passed.\n");
