<?php

declare(strict_types=1);

namespace ItPack\HashIt;

enum HashObservationKind: string
{
    case STARTED = 'started';

    case PROGRESS = 'progress';

    case COMPLETED = 'completed';

    case FAILED = 'failed';
}
