<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\RequireInterface;

interface Contract
{
}

class ParentWithInterface implements Contract
{
}

class Intermediate extends ParentWithInterface
{
}

class Direct implements Contract
{
}

class Inherited extends Intermediate
{
}

class ParentWithoutInterface
{
}

class Missing extends ParentWithoutInterface
{
}
