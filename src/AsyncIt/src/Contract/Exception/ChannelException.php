<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

final class ChannelException extends RuntimeException implements AsyncItExceptionInterface
{
}
