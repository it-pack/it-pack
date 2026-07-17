<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Utility;

use Codeception\Test\Unit;
use Tests\Stubs\Behavior\Utility\LazyCreatableInstance;

class LazyCreatableTest extends Unit
{
    public function testCreateLazy(): void
    {
        $instance = LazyCreatableInstance::createLazy();

        verify(
            $instance,
        )->instanceOf(LazyCreatableInstance::class);

    }
}
