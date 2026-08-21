<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use Codeception\Test\Unit;
use ItPack\CommitIt\Contract\NullRepositoryObserver;
use RuntimeException;

/**
 * @covers \ItPack\CommitIt\Contract\NullRepositoryObserver
 */
final class NullRepositoryObserverTest extends Unit
{
    public function testObserverIsNoOp(): void
    {
        $observer = new NullRepositoryObserver();

        $observer->onStart('commit', ['key' => 'value']);
        $observer->onSuccess('commit', ['id' => 'abc']);
        $observer->onFailure('commit', new RuntimeException('boom'), ['id' => 'abc']);

        self::assertInstanceOf(NullRepositoryObserver::class, $observer);
    }
}
