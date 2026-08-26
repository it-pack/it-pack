<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 */
class ConformanceTester extends \Codeception\Actor
{
    use _generated\ConformanceTesterActions;

    // Define custom actions here
}
