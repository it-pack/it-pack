<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileModificationProbe;

/**
 * @covers \ItPack\ConfigureIt\Adapter\FileModificationProbe
 */
class FileModificationProbeTest extends Unit
{
    private string $file;

    public function testSignatureChangesWhenContentChanges(): void
    {
        $probe = new FileModificationProbe([$this->file]);
        $before = $probe();

        file_put_contents($this->file, 'changed');
        $after = $probe();

        verify($before === $after)->false();
    }

    public function testMissingPathProducesDistinctSignature(): void
    {
        $withMissing = new FileModificationProbe([$this->file, $this->file . '.missing'])->__invoke();
        $withoutMissing = new FileModificationProbe([$this->file])->__invoke();

        verify($withMissing === $withoutMissing)->false();
        verify(ctype_xdigit($withMissing) && 32 === strlen($withMissing))->true();
    }

    protected function _before(): void
    {
        $this->file = sys_get_temp_dir() . '/cfg-probe-' . uniqid();
        file_put_contents($this->file, 'data');
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            @unlink($this->file);
        }
    }
}
