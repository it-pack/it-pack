<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Redis;

/**
 * Minimal client contract for Redis-backed task queue storage.
 *
 * Implementations are injected into {@see \ItPack\AsyncIt\Adapter\Redis\RedisTaskQueue}
 * so the adapter remains vendor-neutral and testable without a live Redis
 * server or the `ext-redis` extension. A `predis/predis` or `ext-redis`
 * client can be wrapped to satisfy this interface.
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTaskQueue
 */
interface RedisClientInterface
{
    /**
     * Sets a field in a hash to a value.
     *
     * @param string $key   Hash key
     * @param string $field Field name
     * @param string $value Field value
     * @return int Number of fields that were added
     */
    public function hSet(string $key, string $field, string $value): int;

    /**
     * Gets the value of a field in a hash.
     *
     * @return string|null Field value, or null if the field does not exist
     */
    public function hGet(string $key, string $field): ?string;

    /**
     * Deletes one or more fields from a hash.
     *
     * @param string $key   Hash key
     * @param string $field Field name
     * @return int Number of fields that were removed
     */
    public function hDel(string $key, string $field): int;

    /**
     * Returns all fields and values of a hash.
     *
     * @return array<string, string> Field => value map, or empty array if key
     *                               does not exist
     */
    public function hGetAll(string $key): array;

    /**
     * Returns the number of fields in a hash.
     *
     * @return int Number of fields, or 0 if key does not exist
     */
    public function hLen(string $key): int;

    /**
     * Inserts a value at the head of a list.
     *
     * @param string $key  List key
     * @param string $value Value to insert
     * @return int Length of the list after the push
     */
    public function lPush(string $key, string $value): int;

    /**
     * Removes and returns the last element of a list.
     *
     * @return string|null The last element, or null if the list is empty
     */
    public function rPop(string $key): ?string;

    /**
     * Removes the first N occurrences of a value from a list.
     *
     * @param string $key   List key
     * @param int    $count Number of occurrences to remove (0 = all)
     * @param string $value Value to remove
     * @return int Number of elements removed
     */
    public function lRem(string $key, int $count, string $value): int;

    /**
     * Iteratively scans fields of a hash, returning a batch per call.
     *
     * @param string $key     Hash key
     * @param string $cursor  Cursor from previous call, or '0' for the first call.
     *                        Updated by reference to the next cursor.
     * @param int    $count   Hint for the maximum number of fields to return.
     * @return array{0: string, 1: array<string, string>} Next cursor and field => value map
     */
    public function hScan(string $key, string &$cursor, int $count): array;

    /**
     * Executes multiple commands in a single round trip (pipeline or MULTI/EXEC).
     *
     * @param list<array<mixed, mixed>> $commands
     *   Each entry is [methodName, arguments] where methodName is one of
     *   hSet, hGet, hDel, hGetAll, hLen, lPush, rPop, lRem, hScan.
     * @return list<mixed> Results in the same order as commands.
     *   hScan results are returned as [cursor, entries].
     */
    public function pipeline(array $commands): array;
}
