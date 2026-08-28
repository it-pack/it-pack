<?php

namespace _Fixture;

class LeakOwnedReturn
{
    public function go(): \_Fixture\LeakBuiltinReturn
    {
    }
}
