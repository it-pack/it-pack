<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Poller;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\PollerVocabulary;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;

/**
 * Native {@see PollerPortInterface} over PHP 8.6 `Io\Poll`.
 *
 * `Io\Poll` is the OS-readiness notifier shipped with PHP 8.6; this adapter
 * slides it into the very same PollerPort slot as the {@see SelectPoller}
 * fallback, so the scheduler and scheduling semantics are unchanged. The
 * adapter is edge-triggered: a stream is registered for READABLE readiness and
 * `await()` drives `Io\Poll::poll()`, re-probing each tick. Every registered
 * handle is tracked so conformance Assertion C observes deterministic cleanup,
 * and `release()` detaches the underlying watcher.
 *
 * The `Io\Poll` instance is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types. On runtimes without
 * `Io\Poll` the {@see \ItPack\AsyncIt\Application\Factory\PollerFactory} selects
 * the `SelectPoller` fallback instead, so the package stays fully usable.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class NativePoller implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    public function __construct(private readonly object $poll)
    {
    }

    public function await(int $timeoutNanoseconds): void
    {
        if (!method_exists($this->poll, 'poll')) {
            return;
        }

        // Io\Poll::poll(?float $timeout) expects seconds, not milliseconds.
        $seconds = $timeoutNanoseconds / PollerVocabulary::NANOSECONDS_PER_SECOND;

        /** @var callable $poll */
        $poll = [$this->poll, 'poll'];
        $entries = ($poll)($seconds);

        if (!is_array($entries)) {
            return;
        }

        $this->readyReadStreams = [];
        $this->readyWriteStreams = [];

        foreach ($entries as $entry) {
            if (!is_object($entry)) {
                continue;
            }

            $stream = $entry->stream ?? null;
            if (!is_resource($stream)) {
                continue;
            }

            if (($entry->readable ?? false) === true) {
                $this->readyReadStreams[] = $stream;
            }

            if (($entry->writable ?? false) === true) {
                $this->readyWriteStreams[] = $stream;
            }
        }
    }

    protected function getHandleIdPrefix(): string
    {
        return 'native-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        $this->registerWithPoll($id, $stream);
    }

    protected function releaseFromReactor(mixed $callbackId): void
    {
        $stream = $callbackId;

        if (!is_resource($stream) || !method_exists($this->poll, 'unregister')) {
            return;
        }

        /** @var callable $unregister */
        $unregister = [$this->poll, 'unregister'];
        ($unregister)($stream);
    }

    private function registerWithPoll(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !method_exists($this->poll, 'register')) {
            return;
        }

        // Register for READABLE readiness using the vocabulary constant.
        /** @var callable $register */
        $register = [$this->poll, 'register'];
        $registered = ($register)($stream, PollerVocabulary::READABLE);

        if ($registered === true) {
            $this->registered[$id] = $stream;
        }
    }
}
