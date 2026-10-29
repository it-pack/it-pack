<?php

declare(strict_types=1);

namespace ItPack\FileIt\ValueObject;

class FileInfo
{
    public int|false $aTime;
    public int|false $mTime;
    public int|false $cTime;
    public int|false $size;
    public string $path;
    public string $name;
    public string $extension;
    public string $type;
    public string $mimeType;
    public string $sha256;
}
