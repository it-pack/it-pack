<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents;

/** CloudEvents 1.0 context attributes and structured JSON data members. */
enum CloudEventAttributeKey: string
{
    case SpecVersion = 'specversion';
    case Id = 'id';
    case Source = 'source';
    case Type = 'type';
    case Subject = 'subject';
    case Time = 'time';
    case DataContentType = 'datacontenttype';
    case DataSchema = 'dataschema';
    case Data = 'data';
    case DataBase64 = 'data_base64';
}
