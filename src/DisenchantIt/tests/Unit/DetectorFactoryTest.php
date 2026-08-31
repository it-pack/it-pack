<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Detection\Detector;
use ItPack\DisenchantIt\Contract\Detection\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Contract\Grouping\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\Grouping\CandidateGrouperInterface;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\ValueObject\Source\SourceCode;

final class DetectorFactoryTest extends Unit
{
    public function testBuildReturnsPureScanner(): void
    {
        $detector = DetectorFactory::create()->build();

        $this->assertInstanceOf(Detector::class, $detector);
        $this->assertInstanceOf(StringLiteralDetectorInterface::class, $detector);
    }

    public function testFactoryYieldsFilterAndGrouperPorts(): void
    {
        $factory = DetectorFactory::create();

        $this->assertInstanceOf(CandidateFilterInterface::class, $factory->buildFilter());
        $this->assertInstanceOf(CandidateGrouperInterface::class, $factory->buildGrouper());
    }

    public function testDetectionPipelineScansFiltersAndGroups(): void
    {
        $factory = DetectorFactory::create();
        $code = <<<'PHP'
            <?php
            class C {
                public function tag(): string {
                    return 'admin-user';
                }
                public function tagAgain(): string {
                    return 'admin-user';
                }
                public function fail(): void {
                    throw new \RuntimeException('boom');
                }
            }
            PHP;

        $literals = $factory->build()->scanSource(new SourceCode($code));
        $filtered = $factory->buildFilter()->filterCandidates($literals);
        $grouped = $factory->buildGrouper()->groupByValue($filtered);

        $this->assertCount(3, $literals, 'scanner returns every literal, unfiltered');
        $this->assertCount(2, $filtered, 'exception message is filtered out');
        $this->assertArrayHasKey('admin-user', $grouped);
        $this->assertCount(2, $grouped['admin-user'], 'repeated literal is grouped');
    }
}
