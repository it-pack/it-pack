<?php

declare(strict_types=1);

namespace ItPack\HashIt\Tests\Unit;

use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\TreeHashingInterface;
use ItPack\HashIt\Filepath;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInput\FileInput;
use ItPack\HashIt\HashInput\StreamInput;
use ItPack\HashIt\HashInput\StringInput;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\HashIt\TreeHasher
 * @covers \ItPack\HashIt\Contract\TreeHashingInterface
 */
final class TreeHasherTest extends TestCase
{
    private TreeHashingInterface $tree;

    protected function setUp(): void
    {
        $this->tree = NativeHashIt::treeHasher();
    }

    public function testSingleChunkEqualsPlainHash(): void
    {
        $data = 'the quick brown fox';
        $input = new StringInput($data);

        $tree = $this->tree->treeHash($input, 1024, HashAlgorithm::SHA256);
        $plain = NativeHashIt::create()->hash($data, HashAlgorithm::SHA256);

        self::assertTrue($tree->equals($plain));
        self::assertSame($plain->value(), $tree->value());
    }

    public function testEmptyInputEqualsPlainHashOfEmptyString(): void
    {
        $tree = $this->tree->treeHash(new StringInput(''), 64, HashAlgorithm::SHA256);
        $plain = NativeHashIt::create()->hash('', HashAlgorithm::SHA256);

        self::assertTrue($tree->equals($plain));
    }

    public function testDeterministicForSameInputAndChunkSize(): void
    {
        $data = random_bytes(200_000);

        $a = $this->tree->treeHash(new StringInput($data), 4096, HashAlgorithm::SHA256);
        $b = $this->tree->treeHash(new StringInput($data), 4096, HashAlgorithm::SHA256);

        self::assertTrue($a->equals($b));
    }

    public function testMultiChunkDiffersFromPlainHash(): void
    {
        $data = random_bytes(200_000);

        $tree = $this->tree->treeHash(new StringInput($data), 4096, HashAlgorithm::SHA256);
        $plain = NativeHashIt::create()->hash($data, HashAlgorithm::SHA256);

        self::assertFalse($tree->equals($plain));
    }

    public function testDifferentChunkSizeYieldsDifferentTreeHash(): void
    {
        $data = random_bytes(200_000);

        $small = $this->tree->treeHash(new StringInput($data), 4096, HashAlgorithm::SHA256);
        $large = $this->tree->treeHash(new StringInput($data), 8192, HashAlgorithm::SHA256);

        self::assertFalse($small->equals($large));
    }

    public function testMatchesIndependentReferenceImplementation(): void
    {
        $data = random_bytes(123_457);
        $algorithm = HashAlgorithm::SHA256;

        $tree = $this->tree->treeHash(new StringInput($data), 10_000, $algorithm);
        $reference = $this->referenceTreeHash($data, 10_000, $algorithm->value);

        self::assertSame($reference, $tree->value());
    }

    public function testStreamAndFileInputsMatchStringInput(): void
    {
        $data = random_bytes(150_000);
        $chunkSize = 8192;

        $fromString = $this->tree->treeHash(new StringInput($data), $chunkSize, HashAlgorithm::SHA256);

        $resource = fopen('php://memory', 'r+b');
        fwrite($resource, $data);
        rewind($resource);
        $fromStream = $this->tree->treeHash(new StreamInput($resource), $chunkSize, HashAlgorithm::SHA256);
        fclose($resource);

        $file = tempnam(sys_get_temp_dir(), 'tree');
        file_put_contents($file, $data);
        $fromFile = $this->tree->treeHash(new FileInput(new Filepath($file)), $chunkSize, HashAlgorithm::SHA256);
        unlink($file);

        self::assertTrue($fromString->equals($fromStream));
        self::assertTrue($fromString->equals($fromFile));
    }

    public function testOddChunkCountCombinesCorrectly(): void
    {
        // 3 chunks → an odd trailing leaf is promoted unchanged, then combined
        // with the first pair's node. Verify against the reference spec.
        $data = random_bytes(30_000);
        $chunkSize = 10_000; // yields 3 leaves

        $tree = $this->tree->treeHash(new StringInput($data), $chunkSize, HashAlgorithm::SHA256);
        $reference = $this->referenceTreeHash($data, $chunkSize, HashAlgorithm::SHA256->value);

        self::assertSame($reference, $tree->value());
    }

    public function testNonPositiveChunkSizeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tree->treeHash(new StringInput('x'), 0, HashAlgorithm::SHA256);
    }

    /**
     * An independent re-implementation of the ADR-0001 combiner using only the
     * native hash() extension (no shared code with TreeHasher), so a passing
     * comparison proves the two agree on the canonical scheme.
     */
    private function referenceTreeHash(string $data, int $chunkSize, string $algorithm): string
    {
        $leaves = [];
        $length = strlen($data);
        for ($offset = 0; $offset < $length || $length === 0; $offset += $chunkSize) {
            $leaves[] = hash($algorithm, substr($data, $offset, $chunkSize), true);
            if ($length === 0) {
                break;
            }
        }

        return bin2hex($this->referenceCombine($leaves, $algorithm));
    }

    /**
     * @param list<string> $nodes
     */
    private function referenceCombine(array $nodes, string $algorithm): string
    {
        if (count($nodes) === 1) {
            return $nodes[0];
        }

        $next = [];
        $count = count($nodes);
        for ($i = 0; $i < $count; $i += 2) {
            if ($i + 1 < $count) {
                $next[] = hash(
                    $algorithm,
                    $this->referenceFrame($nodes[$i]) . $this->referenceFrame($nodes[$i + 1]),
                    true,
                );
            } else {
                $next[] = $nodes[$i];
            }
        }

        return $this->referenceCombine($next, $algorithm);
    }

    private function referenceFrame(string $digest): string
    {
        $bytes = '';
        $remaining = strlen($digest);
        for ($i = 0; $i < 8; $i++) {
            $bytes .= chr($remaining & 0xFF);
            $remaining >>= 8;
        }

        return $bytes . $digest;
    }
}
