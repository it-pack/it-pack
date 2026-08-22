<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony\Tests;

use ItPack\ConfigureItSymfony\ConfigureItBundle;
use ItPack\ConfigureItSymfony\ConfigureItExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class ConfigureItBundleTest extends TestCase
{
    public function testExposesItsExtension(): void
    {
        $bundle = new ConfigureItBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(ConfigureItExtension::class, $extension);
    }
}
