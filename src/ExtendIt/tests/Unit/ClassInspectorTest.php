<?php

declare(strict_types=1);

namespace Tests\Unit;

use ItPack\ExtendIt\Inspection\ClassInspector;
use ReflectionClass;
use stdClass;

class ClassInspectorTest extends \Codeception\Test\Unit
{
    public function testInspectDoesNotThrowOnEmptyClass(): void
    {
        $inspector = new ClassInspector();
        $reflection = new ReflectionClass(stdClass::class);

        $inspector->inspect($reflection);

        $this->assertTrue(true);
    }
}
