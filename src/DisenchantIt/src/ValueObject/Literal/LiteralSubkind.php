<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Literal;

use ItPack\DisenchantIt\Adapter\Detection\ContentClassifier;

/**
 * Closed set of *content* sub-kinds a detected literal can represent, inferred
 * by ContentClassifier from the literal's raw value (and, later, from the
 * surrounding AST for role-aware cases such as status codes). Genuinely closed
 * internal state, so a backed enum (per AGENTS.md / ADR-0003) rather than a
 * bare string.
 *
 * This tag provides the recognition layer that later phases build on: an
 * "enum-shaped" string set, a bitwise flag mask, or a status code all need to
 * know *what the literal is* before a placement target can be chosen. Phase 0
 * only attaches the tag; it changes no filtering, grouping, or rewrite behavior.
 */
enum LiteralSubkind: string
{
    case Unknown = 'unknown';
    case Url = 'url';
    // @phpstan-ignore shipmonk.deadEnumCase
    case FilePath = 'file_path';
    case Regex = 'regex';
    case Sql = 'sql';
    case Json = 'json';
    // @phpstan-ignore shipmonk.deadEnumCase
    case DateTimeFormat = 'date_time_format';
    // @phpstan-ignore shipmonk.deadEnumCase
    case Locale = 'locale';
    // @phpstan-ignore shipmonk.deadEnumCase
    case Charset = 'charset';
    // @phpstan-ignore shipmonk.deadEnumCase
    case MimeType = 'mime_type';
    case Version = 'version';
    // @phpstan-ignore shipmonk.deadEnumCase
    case HashAlgorithm = 'hash_algorithm';
    // @phpstan-ignore shipmonk.deadEnumCase
    case HttpMethod = 'http_method';
    // @phpstan-ignore shipmonk.deadEnumCase
    case HttpStatus = 'http_status';
    // @phpstan-ignore shipmonk.deadEnumCase
    case EventTag = 'event_tag';
    // @phpstan-ignore shipmonk.deadEnumCase
    case ConfigKey = 'config_key';
    case Bitmask = 'bitmask';
    case StatusCode = 'status_code';
    // @phpstan-ignore shipmonk.deadEnumCase
    case TimeUnit = 'time_unit';
    // @phpstan-ignore shipmonk.deadEnumCase
    case Size = 'size';
    // @phpstan-ignore shipmonk.deadEnumCase
    case Port = 'port';
}
