<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

enum UriScheme: string
{
    case File = 'file';
    case Http = 'http';
    case Https = 'https';
    case Ftp = 'ftp';
    case Ftps = 'ftps';
    case None = '';
}
