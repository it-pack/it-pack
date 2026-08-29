<?php

declare(strict_types=1);

namespace ItPack\HashIt;

enum HashOperation: string
{
    case HASH = 'HASH';
    case HASH_BINARY = 'HASH_BINARY';
    case HASH_FILE = 'HASH_FILE';
    case HASH_STREAM = 'HASH_STREAM';
    case HASH_FILES = 'HASH_FILES';
    case HASH_DIRECTORY = 'HASH_DIRECTORY';
    case HMAC = 'HMAC';
    case HMAC_FILE = 'HMAC_FILE';
    case HMAC_STREAM = 'HMAC_STREAM';
    case HMAC_FILES = 'HMAC_FILES';
    case VERIFY = 'VERIFY';
}
