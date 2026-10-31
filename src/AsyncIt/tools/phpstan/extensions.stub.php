<?php

declare(strict_types=1);

/**
 * PHPStan stubs for runtime/extension classes that may be absent in the
 * analysis environment (PHP 8.6 `Io\Poll`, the Swoole and Swow extensions).
 * They exist only to satisfy static analysis; they are never autoloaded
 * at runtime.
 */

namespace Io {
    final class Poll
    {
    }
}

namespace Swow {
    final class Event
    {
        public const TYPE_READ = 1;
        public const TYPE_WRITE = 2;
        public const TYPE_SOCKET = 4;

        /**
         * @param mixed $coroutineOrStream
         * @param int|null $flags
         * @param callable|null $callback
         * @param int $type
         * @return mixed
         */
        public static function add($coroutineOrStream, ?int $flags = null, ?callable $callback = null, int $type = 0): mixed
        {
            return null;
        }

        public static function del($stream): void
        {
        }

        public static function wait(): void
        {
        }
    }
}

namespace Swoole\Coroutine {
    final class Channel
    {
        public function __construct(int $size = 0)
        {
        }

        public function push(mixed $data, float $timeout = -1): bool
        {
            return true;
        }

        public function pop(float $timeout = -1): mixed
        {
            return null;
        }

        public function close(): void
        {
        }

        public function isFull(): bool
        {
            return false;
        }

        public function isEmpty(): bool
        {
            return true;
        }

        public function length(): int
        {
            return 0;
        }
    }
}

namespace Swow\Coroutine {
    final class Channel
    {
        public function __construct(int $size = 0)
        {
        }

        public function push(mixed $data, float $timeout = -1): bool
        {
            return true;
        }

        public function pop(float $timeout = -1): mixed
        {
            return null;
        }

        public function close(): void
        {
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function isFull(): bool
        {
            return false;
        }

        public function isEmpty(): bool
        {
            return true;
        }

        public function length(): int
        {
            return 0;
        }
    }
}
