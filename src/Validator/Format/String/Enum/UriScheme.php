<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String\Enum;

enum UriScheme: string
{
    case Http = 'http';
    case Https = 'https';
    case Ftp = 'ftp';
    case Ftps = 'ftps';
    case File = 'file';
    case Mailto = 'mailto';
    case Tel = 'tel';
    case Data = 'data';
}
