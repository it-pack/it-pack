<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;

/**
 * Contract for recording measurements bound to the ambient execution (and activity).
 *
 * The recorder fills ownership, timing, and sequence automatically; the caller
 * supplies only the measurable facts. The recorded measurement is published before
 * it is returned.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#measurement
 */
interface MeasurementRecorderInterface
{
    /**
     * @param string|MeasurementNameInterface $name
     * @param int $value
     * @param MeasurementUnitInterface|null $unit
     * @param AttributeCollectionInterface|null $attributes
     * @return MeasurementInterface
     */
    public function counter(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface;

    /**
     * @param string|MeasurementNameInterface $name
     * @param float $value
     * @param MeasurementUnitInterface|null $unit
     * @param AttributeCollectionInterface|null $attributes
     * @return MeasurementInterface
     */
    public function gauge(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface;

    /**
     * @param string|MeasurementNameInterface $name
     * @param float $value
     * @param MeasurementUnitInterface|null $unit
     * @param AttributeCollectionInterface|null $attributes
     * @return MeasurementInterface
     */
    public function duration(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface;

    /**
     * @param string|MeasurementNameInterface $name
     * @param int $value
     * @param MeasurementUnitInterface|null $unit
     * @param AttributeCollectionInterface|null $attributes
     * @return MeasurementInterface
     */
    public function size(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface;

    /**
     * @param string|MeasurementNameInterface $name
     * @param float $value
     * @param MeasurementUnitInterface|null $unit
     * @param AttributeCollectionInterface|null $attributes
     * @return MeasurementInterface
     */
    public function ratio(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface;
}
