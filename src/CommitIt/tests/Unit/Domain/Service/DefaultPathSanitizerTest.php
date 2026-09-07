<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Exception\PathScopeViolationException;
use ItPack\CommitIt\Domain\Service\DefaultPathSanitizer;

/**
 * @covers \ItPack\CommitIt\Domain\Service\DefaultPathSanitizer
 */
final class DefaultPathSanitizerTest extends Unit
{
    private string $repoRoot;

    protected function _before(): void
    {
        $this->repoRoot = sys_get_temp_dir() . '/commitit-sanitizer-test-' . bin2hex(random_bytes(8));
        mkdir($this->repoRoot, 0o755, true);
    }

    protected function _after(): void
    {
        if (is_dir($this->repoRoot)) {
            $this->removeDirectory($this->repoRoot);
        }
    }

    public function testRejectsParentTraversal(): void
    {
        $sanitizer = new DefaultPathSanitizer();

        $this->expectException(PathScopeViolationException::class);
        $sanitizer->sanitize('../../../../etc/passwd', $this->repoRoot);
    }

    public function testRejectsAbsolutePathOutsideRepo(): void
    {
        $sanitizer = new DefaultPathSanitizer();

        $this->expectException(PathScopeViolationException::class);
        $sanitizer->sanitize('/etc/passwd', $this->repoRoot);
    }

    public function testAcceptsRelativePathInsideRepo(): void
    {
        $sanitizer = new DefaultPathSanitizer();
        $result = $sanitizer->sanitize('src/file.txt', $this->repoRoot);

        self::assertSame('src/file.txt', $result);
    }

    public function testAcceptsNestedRelativePath(): void
    {
        $sanitizer = new DefaultPathSanitizer();
        $result = $sanitizer->sanitize('a/b/c/file.txt', $this->repoRoot);

        self::assertSame('a/b/c/file.txt', $result);
    }

    public function testRejectsTraversalViaDotDot(): void
    {
        $sanitizer = new DefaultPathSanitizer();

        $this->expectException(PathScopeViolationException::class);
        $sanitizer->sanitize('foo/../../../etc/shadow', $this->repoRoot);
    }

    public function testDotSegmentsAreNormalized(): void
    {
        $sanitizer = new DefaultPathSanitizer();
        $result = $sanitizer->sanitize('src/../src/./file.txt', $this->repoRoot);

        self::assertSame('src/file.txt', $result);
    }

    public function testRejectsNonExistentRepoRoot(): void
    {
        $sanitizer = new DefaultPathSanitizer();

        $this->expectException(PathScopeViolationException::class);
        $sanitizer->sanitize('file.txt', '/nonexistent/repo/path');
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
