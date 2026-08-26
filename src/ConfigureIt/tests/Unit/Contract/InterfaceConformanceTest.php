<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\MessageFactory;
use ItPack\ConfigureIt\Contract\ConfigurationBuilderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDiagnosticsInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDifferInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoadersInterface;
use ItPack\ConfigureIt\Contract\MessageFactoryInterface;
use ItPack\ConfigureIt\Contract\ReloadableConfigurationInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationDiffInterface;
use ItPack\ConfigureIt\Contract\ValueObject\DiagnosticsReportInterface;
use ItPack\ConfigureIt\Contract\ValueObject\FlagDefinitionInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationDiagnostics;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ReloadableConfiguration;
use ItPack\ConfigureIt\ValueObject\ConfigurationDiff;
use ItPack\ConfigureIt\ValueObject\DiagnosticsReport;
use ItPack\ConfigureIt\ValueObject\FlagDefinition;
use ReflectionClass;

/**
 * Conformance guard for the Milestone-5 interface extraction (NV8).
 *
 * Each open cross-boundary service / value object now backs its behaviour with
 * a contract; this test fails if any concrete regresses to a headless class so
 * the "open services expose interfaces" policy cannot silently erode.
 */
class InterfaceConformanceTest extends Unit
{
    public function testOpenServicesExposeTheirContracts(): void
    {
        $cases = [
            [MessageFactory::class, MessageFactoryInterface::class],
            [ConfigurationLoaders::class, ConfigurationLoadersInterface::class],
            [ConfigurationBuilder::class, ConfigurationBuilderInterface::class],
            [ReloadableConfiguration::class, ReloadableConfigurationInterface::class],
            [ConfigurationDiffer::class, ConfigurationDifferInterface::class],
            [ConfigurationDiagnostics::class, ConfigurationDiagnosticsInterface::class],
            [ConfigurationDiff::class, ConfigurationDiffInterface::class],
            [DiagnosticsReport::class, DiagnosticsReportInterface::class],
            [FlagDefinition::class, FlagDefinitionInterface::class],
        ];

        foreach ($cases as [$concrete, $contract]) {
            self::assertTrue(
                new ReflectionClass($concrete)->implementsInterface($contract),
                sprintf('%s must implement %s', $concrete, $contract),
            );
        }
    }
}
