<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Redis;

use ItPack\AsyncIt\Adapter\Redis\RedisClientInterface;

final class InMemoryRedisClient implements RedisClientInterface
{
    /** @var array<string, array<string, string>> hash => field => value */
    private array $hashes = [];

    /** @var array<string, list<string>> list key => values (head first) */
    private array $lists = [];

    public function hSet(string $key, string $field, string $value): int
    {
        $this->hashes[$key][$field] = $value;

        return array_key_exists($key, $this->hashes) && array_key_exists($field, $this->hashes[$key]) ? 1 : 0;
    }

    public function hSetNx(string $key, string $field, string $value): int
    {
        if (!array_key_exists($key, $this->hashes)) {
            $this->hashes[$key] = [];
        }

        if (array_key_exists($field, $this->hashes[$key])) {
            return 0;
        }

        $this->hashes[$key][$field] = $value;

        return 1;
    }

    public function hGet(string $key, string $field): ?string
    {
        return array_key_exists($key, $this->hashes) && array_key_exists($field, $this->hashes[$key])
            ? $this->hashes[$key][$field]
            : null;
    }

    public function hDel(string $key, string $field): int
    {
        if (!array_key_exists($key, $this->hashes) || !array_key_exists($field, $this->hashes[$key])) {
            return 0;
        }

        unset($this->hashes[$key][$field]);

        return 1;
    }

    public function hGetAll(string $key): array
    {
        return array_key_exists($key, $this->hashes) ? $this->hashes[$key] : [];
    }

    public function hLen(string $key): int
    {
        return array_key_exists($key, $this->hashes) ? count($this->hashes[$key]) : 0;
    }

    public function lPush(string $key, string $value): int
    {
        if (!array_key_exists($key, $this->lists)) {
            $this->lists[$key] = [];
        }

        array_unshift($this->lists[$key], $value);

        return count($this->lists[$key]);
    }

    public function rPop(string $key): ?string
    {
        if (!array_key_exists($key, $this->lists) || $this->lists[$key] === []) {
            return null;
        }

        return array_pop($this->lists[$key]);
    }

    public function lRem(string $key, int $count, string $value): int
    {
        if (!array_key_exists($key, $this->lists)) {
            return 0;
        }

        $removed = 0;
        $filtered = [];
        foreach ($this->lists[$key] as $v) {
            if ($v === $value && ($count === 0 || $removed < $count)) {
                $removed++;
                continue;
            }

            $filtered[] = $v;
        }

        $this->lists[$key] = $filtered;

        return $removed;
    }

    public function hScan(string $key, string &$cursor, int $count): array
    {
        $hash = $this->hashes[$key] ?? [];
        $fields = array_keys($hash);
        $start = (int) $cursor;
        $end = min($start + $count, count($fields));
        $entries = [];
        for ($i = $start; $i < $end; $i++) {
            $field = $fields[$i];
            $entries[$field] = $hash[$field];
        }

        $cursor = $end >= count($fields) ? '0' : (string) $end;

        return [$cursor, $entries];
    }

    /**
     * @param list<array<mixed, mixed>> $commands
     * @return list<mixed>
     */
    public function pipeline(array $commands): array
    {
        $results = [];
        foreach ($commands as $cmd) {
            $method = $cmd[0];
            $args = $cmd[1];
            /** @phpstan-ignore-next-line method.dynamicName */
            $results[] = $this->$method(...$args);
        }

        return $results;
    }
}
