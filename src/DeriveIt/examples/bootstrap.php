<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the DeriveIt runnable examples.
 *
 * Loads Composer's autoloader (which also autoloads the `_Fixture\*` demo
 * classes), and provides a tiny `show()` helper that prints a labelled section
 * with the rendered interface source and an optional takeaway note.
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\DeriveIt\DeriveIt;

if (!function_exists('show')) {
    /**
     * @param string      $title  Section heading.
     * @param string      $source Rendered interface/adapter source to display.
     * @param string|null $note   One-line takeaway shown after the source.
     */
    function show(string $title, string $source, ?string $note = null): void
    {
        echo "\n\033[1;36m" . str_repeat('=', 78) . "\033[0m\n";
        echo "\033[1;36m" . $title . "\033[0m\n";
        echo "\033[1;36m" . str_repeat('=', 78) . "\033[0m\n";
        echo $source . "\n";

        if ($note !== null) {
            echo "\033[1;32m→ " . $note . "\033[0m\n";
        }
    }
}
