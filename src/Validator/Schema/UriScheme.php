<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

/**
 * URI scheme classification used by the builtin FileExternalRefResolver.
 *
 * Network schemes (Http, Https, Ftp, Ftps) are denied by the builtin resolver
 * to prevent SSRF; users must inject a custom ExternalRefResolverInterface
 * implementation to resolve them.
 */
enum UriScheme: string
{
    case File = 'file';
    case Http = 'http';
    case Https = 'https';
    case Ftp = 'ftp';
    case Ftps = 'ftps';
    case None = '';
}
