<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\HashProgressInterface;
use ItPack\HashIt\HashProgress;

class HashProgressTest extends Unit
{
    private function spy(array &$records): HashProgressInterface
    {
        return HashProgress::fromCallable(
            static function (int $processed, ?int $total) use (&$records): void {
                $records[] = [$processed, $total];
            },
        );
    }

    public function testStreamProgressIsMonotonicAndFinalMatchesTotal(): void
    {
        $data = random_bytes(200000); // > 3 chunks of 65536
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $data);
        rewind($stream);

        $records = [];
        $progress = $this->spy($records);

        $hash = NativeHashIt::create()->hashStream($stream, 'sha256', 65536, $progress);

        verify($hash->value())->equals(hash('sha256', $data));
        verify($records)->notEmpty();
        $this->assertProcessedMonotonic($records);

        $last = $records[count($records) - 1];
        verify($last[0])->equals(strlen($data));
        verify($last[1])->equals(strlen($data));

        fclose($stream);
    }

    public function testFileProgressReportsKnownTotal(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($file, 'hello world');
        $path = new \ItPack\HashIt\Adapter\NativeFilepathFileinfo(new \SplFileInfo($file));

        $records = [];
        $progress = $this->spy($records);

        $result = NativeHashIt::create()->hashFiles([$path], 'sha256', $progress);
        $hash = $result[(string) $path];

        verify($hash->value())->equals(hash_file('sha256', $file));

        $last = $records[count($records) - 1];
        verify($last[0])->equals(11);
        verify($last[1])->equals(11);

        unlink($file);
    }

    public function testHashFilesProgressTotalIsSumOfFileSizes(): void
    {
        $dir = sys_get_temp_dir() . '/hashit-' . bin2hex(random_bytes(8));
        mkdir($dir);
        $a = $dir . '/a.bin';
        $b = $dir . '/b.bin';
        file_put_contents($a, 'abcdef');          // 6 bytes
        file_put_contents($b, '1234567890');      // 10 bytes

        $records = [];
        $progress = $this->spy($records);

        $result = NativeHashIt::create()->hashDirectory($dir, 'sha256', $progress);

        verify(count($result))->equals(2);
        $this->assertProcessedMonotonic($records);

        $last = $records[count($records) - 1];
        verify($last[0])->equals(16);
        verify($last[1])->equals(16);

        unlink($a);
        unlink($b);
        rmdir($dir);
    }

    public function testHmacStreamProgressWired(): void
    {
        $data = random_bytes(150000);
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $data);
        rewind($stream);

        $records = [];
        $progress = $this->spy($records);

        $hash = NativeHashIt::keyedHasher()->hmacStream($stream, 'secret-key', 'sha256', 65536, $progress);

        verify($hash->value())->equals(hash_hmac('sha256', $data, 'secret-key'));
        verify($records)->notEmpty();
        $this->assertProcessedMonotonic($records);

        $last = $records[count($records) - 1];
        verify($last[0])->equals(strlen($data));
        verify($last[1])->equals(strlen($data));

        fclose($stream);
    }

    public function testNullProgressLeavesBehaviourUnchanged(): void
    {
        $data = 'consistent digest regardless of progress wiring';

        $ignored = [];
        $withProgress = NativeHashIt::create()->hashStream(
            $this->openMemory($data),
            'sha256',
            32,
            $this->spy($ignored),
        );

        $withoutProgress = NativeHashIt::create()->hashStream($this->openMemory($data), 'sha256', 32);

        verify($withProgress->value())->equals($withoutProgress->value());
        verify($withProgress->value())->equals(hash('sha256', $data));
    }

    private function openMemory(string $data)
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }

    /**
     * @param array<int, array{0: int, 1: ?int}> $records
     */
    private function assertProcessedMonotonic(array $records): void
    {
        $previous = 0;
        foreach ($records as $tuple) {
            static::assertGreaterThanOrEqual($previous, $tuple[0]);
            $previous = $tuple[0];
        }
    }
}
