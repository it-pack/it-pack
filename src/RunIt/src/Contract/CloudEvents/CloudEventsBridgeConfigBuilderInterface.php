<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\CloudEvents;

/**
 * Contract for building CloudEvents bridge configuration.
 *
 * @see https://cloudevents.io/
 */
interface CloudEventsBridgeConfigBuilderInterface
{
    public function withSink(object $sink): self;

    public function withPolicy(object $policy): self;

    public function withMapper(object $mapper): self;

    public function build(): object;
}
