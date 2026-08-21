<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;

final class NoopMeasurementRecorder implements MeasurementRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    private function measurement(): MeasurementInterface
    {
        return \ItPack\RunIt\Domain\Measurement\Measurement::counter(
            \ItPack\RunIt\Domain\Measurement\MeasurementName::fromString('noop'),
            $this->executionId,
            0,
        );
    }

    public function counter(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, int $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function gauge(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function duration(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function size(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, int $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }

    public function ratio(string|\ItPack\RunIt\Contract\Domain\MeasurementNameInterface $name, float $value, ?\ItPack\RunIt\Contract\Domain\MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->measurement();
    }
}
