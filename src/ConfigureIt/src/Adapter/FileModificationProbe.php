<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

/**
 * Computes a stable signature of a set of local files for change detection.
 *
 * Used by {@see PollingWatcher} as the default mechanism: the signature is
 * derived from each path's modification time and size, so any edit is observed
 * on the next poll. Inject a different callable to watch non-filesystem targets
 * (e.g. an HTTP ETag) without touching the watcher.
 */
final class FileModificationProbe
{
    /**
     * @param array<int, string> $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {
    }

    /**
     * @return string stable signature that changes when any watched file is added, removed or modified.
     */
    public function __invoke(): string
    {
        $parts = [];

        foreach ($this->paths as $path) {
            if (is_file($path)) {
                $parts[] = $path . ':' . md5_file($path);
            } else {
                $parts[] = $path . ':missing';
            }
        }

        return md5(implode('|', $parts));
    }
}
