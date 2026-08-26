<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextToken;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;

use function Swoole\Coroutine\run;

use Tests\Support\Fixture\RunItTestKit;

/**
 * M11.5 — coroutine-local execution context storage.
 */
final class CoroutineContextStorageTest extends Unit
{
    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('coroutine'));
    }

    private function captureScope(): ExecutionScopeInterface
    {
        $kit = new RunItTestKit();
        $captured = null;
        $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (&$captured): void {
            $captured = $scope;
        });

        self::assertInstanceOf(ExecutionScopeInterface::class, $captured);

        return $captured;
    }

    public function testAttachCurrentAndDetachForFixedCoroutine(): void
    {
        $storage = new CoroutineContextStorage(new FixedCoroutineIdProvider(1));
        $scope = $this->captureScope();

        $token = $storage->attach($scope);
        self::assertSame($scope, $storage->current());

        $storage->detach($token);
        self::assertNull($storage->current());
    }

    public function testDetachingAnAlreadyDetachedTokenIsRejected(): void
    {
        $storage = new CoroutineContextStorage(new FixedCoroutineIdProvider(1));
        $token = $storage->attach($this->captureScope());

        $storage->detach($token);

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($token);
    }

    public function testForeignCoroutineTokenIsRejected(): void
    {
        $storage = new CoroutineContextStorage(new FixedCoroutineIdProvider(1));
        $storage->attach($this->captureScope());

        $foreign = $storage->attach($this->captureScope());
        // simulate a token issued for a different coroutine id
        $rogue = new CoroutineContextToken(99, $this->captureScope());

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($rogue);
        unset($foreign);
    }

    public function testSequentialCoroutinesStayIsolated(): void
    {
        $kit = new RunItTestKit(storage: new CoroutineContextStorage(new SwooleCoroutineIdProvider()));
        $ids = [];

        run(static function () use ($kit, &$ids): void {
            $kit->runner->run(ExecutionDefinition::create(
                ExecutionName::fromString('request-a'),
                ExecutionKind::fromString('coroutine')
            ), static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['a'] = $scope->execution()->id()->toString();
            });
        });
        run(static function () use ($kit, &$ids): void {
            $kit->runner->run(ExecutionDefinition::create(
                ExecutionName::fromString('request-b'),
                ExecutionKind::fromString('coroutine')
            ), static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['b'] = $scope->execution()->id()->toString();
            });
        });

        self::assertArrayHasKey('a', $ids);
        self::assertArrayHasKey('b', $ids);
        self::assertNotSame($ids['a'], $ids['b']);
        self::assertNull($kit->storage->current(), 'coroutine context must not leak to the main stack');
    }
}

final class FixedCoroutineIdProvider implements CoroutineIdProviderInterface
{
    public function __construct(private int $cid)
    {
    }

    public function currentCoroutineId(): int
    {
        return $this->cid;
    }
}
