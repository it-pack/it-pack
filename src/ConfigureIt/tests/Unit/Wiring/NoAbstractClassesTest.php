<?php

declare(strict_types=1);

namespace Tests\Unit\Wiring;

use Codeception\Test\Unit;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture guard: ConfigureIt admits no abstract classes.
 *
 * The former {@see \ItPack\ConfigureIt\Core\AbstractNodeRewritingProcessor} was
 * the last abstract base; it was decomposed into the injected
 * {@see \ItPack\ConfigureIt\Core\ConfigurationGraphRewriter} collaborator. This
 * test fails the build (not just lint) if a new `abstract` declaration ever
 * reappears under src/, so the policy cannot silently regress.
 */
class NoAbstractClassesTest extends Unit
{
    public function testNoAbstractClassDeclarationsExist(): void
    {
        $root = dirname(__DIR__, 3) . '/src';
        $offending = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (false === $source) {
                continue;
            }

            $tokens = token_get_all($source);

            foreach ($tokens as $token) {
                if (is_array($token) && T_ABSTRACT === $token[0]) {
                    $offending[] = $file->getPathname();
                    break;
                }
            }
        }

        verify($offending)->equals([]);
    }
}
