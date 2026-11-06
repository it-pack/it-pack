<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * PSR-14 event dispatcher that records dispatched events and invokes registered
 * listeners in registration order, honouring stop-propagation.
 */
final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<callable(object): void>
     */
    private array $listeners = [];

    /**
     * @var list<object>
     */
    private array $dispatched = [];

    /**
     * @var list<string>
     */
    private array $listenerCalls = [];

    public function addListener(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        foreach ($this->listeners as $index => $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $this->listenerCalls[] = 'listener_' . $index;
            ($listener)($event);
        }

        return $event;
    }

    /**
     * @return list<object>
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }

    /**
     * @return list<string>
     */
    public function listenerCalls(): array
    {
        return $this->listenerCalls;
    }
}
