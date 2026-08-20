<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Marker implemented by every exception DisenchantIt raises. Lets consumers
 * catch "anything from DisenchantIt" without depending on concrete exception
 * classes, and keeps the domain's failure taxonomy in one place.
 */
interface DisenchantItExceptionInterface
{
}
