<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\SDK;

use ItPack\RunIt\SDK\Attribute\ExecutedAs;

class SampleService
{
    #[ExecutedAs('service.run', 'operation')]
    public function run(): void
    {
    }

    #[ExecutedAs('service.static', 'task')]
    public static function statically(): void
    {
    }

    public function plain(): void
    {
    }
}
