<?php

declare(strict_types=1);

namespace Tests\Support;

use Codeception\Actor;

/**
 * Inherited Methods.
 *
 * @method void wantTo($text)
 * @method void haveFakeHttp()
 */
class IntegrationTester extends Actor
{
    use _generated\IntegrationTesterActions;
}
