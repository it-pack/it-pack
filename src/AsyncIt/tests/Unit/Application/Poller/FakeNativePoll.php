<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Poller;

/**
 * Fake `Io\Poll` implementation for testing {@see NativePoller}.
 */
final class FakeNativePoll
{
    /** @var list<mixed> */
    public array $registered = [];

    /** @var list<mixed> */
    public array $unregistered = [];

    public int $pollCalls = 0;

    public function register(mixed $stream): bool
    {
        $this->registered[] = $stream;

        return true;
    }

    public function unregister(mixed $stream): bool
    {
        $this->unregistered[] = $stream;

        return true;
    }

    /**
     * @return array<int, mixed>
     */
    public function poll(): array
    {
        $this->pollCalls++;
        return [];
    }
}
