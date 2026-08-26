<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use function basename;

use Codeception\Test\Unit;

use function is_file;
use function is_string;

use ItPack\ConfigureIt\Core\TempFileFactory;
use ItPack\ConfigureIt\Exception\ParseException;

use function str_starts_with;
use function unlink;

/**
 * @covers \ItPack\ConfigureIt\Core\TempFileFactory
 * @covers \ItPack\ConfigureIt\Behavior\CreatableBehavior
 */
final class TempFileFactoryTest extends Unit
{
    public function testBuildCreatesTempFileWithPrefix(): void
    {
        $temp = new TempFileFactory('unit-prefix-')->build();

        verify(is_string($temp))->true();
        verify('' !== $temp)->true();
        self::assertNotNull($temp);
        verify(str_starts_with(basename($temp), 'unit-prefix-'))->true();

        if (is_file($temp)) {
            @unlink($temp);
        }
    }

    public function testRequireThrowsWhenBuildFails(): void
    {
        $factory = new class ('x') extends TempFileFactory {
            public function build(): ?string
            {
                return null;
            }
        };

        $this->expectException(ParseException::class);

        $factory->require();
    }

    public function testCreateFactoryAppliesDefaultPrefix(): void
    {
        $factory = TempFileFactory::create();

        self::assertInstanceOf(TempFileFactory::class, $factory);
        verify($factory->tempFilePrefix)->equals(TempFileFactory::DEFAULT_TEMP_FILE_PREFIX);
    }

    public function testCreateFactoryUsesProvidedPrefix(): void
    {
        $factory = TempFileFactory::create('custom-');

        verify($factory->tempFilePrefix)->equals('custom-');
    }
}
