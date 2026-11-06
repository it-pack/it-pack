<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;

final readonly class NoopMeasurementRecorder implements MeasurementRecorderInterface
{
    public function __construct(private ExecutionIdInterface $executionId)
    {
    }

    private function measurement(): MeasurementInterface
    {
        return Measurement::counter(
            MeasurementName::fromString('noop'),
            $this->executionId,
            0,
        );
    }

    public function counter(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->measurement();
    }

    public function gauge(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->measurement();
    }

    public function duration(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->measurement();
    }

    public function size(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->measurement();
    }

    public function ratio(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->measurement();
    }
}
