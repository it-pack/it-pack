<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the examples.
 *
 * Defines small console helpers so each example reads like a narrated script.
 * This file is not an example by itself; run the numbered files instead.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

if (!function_exists('println')) {
    function println(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }
}

if (!function_exists('section')) {
    function section(string $title): void
    {
        $rule = str_repeat('─', max(0, 70 - \mb_strlen($title)));
        println();
        println('■ ' . $title . ' ' . $rule);
    }
}

if (!function_exists('pp')) {
    function pp(mixed $value): void
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        println($encoded === false ? var_export($value, true) : $encoded);
    }
}

if (!function_exists('findOccurrence')) {
    /**
     * Returns the first collected reporter entry of a given occurrence type.
     *
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>|null
     */
    function findOccurrence(array $entries, string $type): ?array
    {
        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) === 'occurrence' && ($entry['name'] ?? null) === $type) {
                return $entry;
            }
        }

        return null;
    }
}

if (!function_exists('executionIdFrom')) {
    /**
     * Pulls the execution id out of a lifecycle occurrence's context.
     *
     * @param array<string, mixed> $entry
     */
    function executionIdFrom(array $entry): ?string
    {
        return $entry['context']['execution_id'] ?? null;
    }
}

// Re-export so examples can reference the constant without a long use chain.
\define('OCC_RECEIVED', ExecutionReporterInterface::COMMAND_RECEIVED);
\define('OCC_HANDLED', ExecutionReporterInterface::COMMAND_HANDLED);
\define('OCC_FAILED', ExecutionReporterInterface::COMMAND_FAILED);
