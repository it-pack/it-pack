<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Application\Federation\ContextAttacher;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\ContextAttacher
 */
final class ContextAttacherTest extends TestCase
{
    public function testAttachesAndDetachesScopeAroundNext(): void
    {
        $flag = new DetachedFlag();

        $contextStorage = new ContextStorageFake($flag);
        $scopeFactory = new class () implements ExecutionScopeFactoryInterface {
            public function create(string|Stringable $discriminant): ExecutionScopeInterface
            {
                return new ReferenceExecutionScope((string) $discriminant);
            }
        };

        $attacher = new ContextAttacher($contextStorage, $scopeFactory);
        $task = new ContextAttachFakeTask('t1');

        $result = $attacher->process($task, fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertTrue($flag->detached);
    }

    public function testDetachesOnException(): void
    {
        $flag = new DetachedFlag();

        $contextStorage = new ContextStorageFake($flag);
        $scopeFactory = new class () implements ExecutionScopeFactoryInterface {
            public function create(string|Stringable $discriminant): ExecutionScopeInterface
            {
                return new ReferenceExecutionScope((string) $discriminant);
            }
        };

        $attacher = new ContextAttacher($contextStorage, $scopeFactory);
        $task = new ContextAttachFakeTask('t1');

        $this->expectException(RuntimeException::class);
        $attacher->process($task, function (): string {
            throw new RuntimeException('boom');
        });

        self::assertTrue($flag->detached);
    }
}
