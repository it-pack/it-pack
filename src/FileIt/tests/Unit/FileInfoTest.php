<?php

declare(strict_types=1);

namespace Tests\Unit;

use ItPack\FileIt\ValueObject\FileInfo;

class FileInfoTest extends \Codeception\Test\Unit
{
    public function testFileInfoIsInstantiable(): void
    {
        $info = new FileInfo();

        $this->assertInstanceOf(FileInfo::class, $info);
    }

    public function testFileInfoHasMutableProperties(): void
    {
        $info = new FileInfo();
        $info->path = '/tmp/test';
        $info->name = 'test';
        $info->size = 42;

        $this->assertSame('/tmp/test', $info->path);
        $this->assertSame('test', $info->name);
        $this->assertSame(42, $info->size);
    }
}
