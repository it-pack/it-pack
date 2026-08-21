# Recipe: HTTP request

Run each HTTP request inside one RunIt execution using the PSR-15
`ExecutionMiddleware`. The middleware derives a cardinality-safe execution name
(`METHOD /normalized-path`), records the response status as an observation, and
enriches the response with correlation headers. It restores the prior context on
every path via the runner's `finally`.

Requires the PSR HTTP packages (`psr/http-message`, `psr/http-server-handler`,
`psr/http-server-middleware`); see [`../integrations/psr.md`](../integrations/psr.md).

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Psr\Http\ExecutionMiddleware;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$middleware = new ExecutionMiddleware($runtime->executionRunner());

$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // application code runs with an ambient execution + execution-aware logger
        return /* a PSR-7 ResponseInterface */;
    }
};

$response = $middleware->process($request, $handler);
// $response now carries X-Execution-Id (and X-Correlation-Id when present)
```

For outbound requests, decorate a PSR-18 client with
`Psr18CorrelationClientDecorator` to propagate correlation headers. In a coroutine
worker, combine this with `ServerExecutionMiddleware` from
[`../integrations/swoole.md`](../integrations/swoole.md).

See [`../concepts/execution.md`](../concepts/execution.md) and
[`../integrations/psr.md`](../integrations/psr.md).
