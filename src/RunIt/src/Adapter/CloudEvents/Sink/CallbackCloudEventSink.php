<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Sink;

use Closure;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Contract\Adapter\CloudEvents\CloudEventInterface;

/**
 * Sink that forwards every CloudEvent to a caller-supplied callback.
 *
 * Useful for in-memory capture in tests, local buffers, and custom transport
 * wiring. The callback receives the immutable event and must not mutate it.
 *
 * @see https://cloudevents.io/
 */
final readonly class CallbackCloudEventSink implements CloudEventSinkInterface
{
    /**
     * @param callable(CloudEventInterface): void $callback Any callable, for example
     *     a closure, an `[$object, 'method']` array callable, or an invokable object.
     *     Stored internally as a {@see Closure}.
     */
    private Closure $callback;

    public function __construct(
        mixed $callback,
    ) {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('The callback must be a valid callable.');
        }
        $this->callback = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
    }

    public function send(CloudEventInterface $event): void
    {
        ($this->callback)($event);
    }
}
