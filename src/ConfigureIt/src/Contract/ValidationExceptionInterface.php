<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface ValidationExceptionInterface
{
    /** @return list<string> */
    public function getMessages(): array;


    /** @return list<MessageInterface> */
    public function getMessageObjects(): array;
}