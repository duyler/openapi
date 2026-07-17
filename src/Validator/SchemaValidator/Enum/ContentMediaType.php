<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Enum;

enum ContentMediaType: string
{
    case ApplicationJson = 'application/json';
    case ApplicationXml = 'application/xml';
    case TextPlain = 'text/plain';
    case TextHtml = 'text/html';
    case TextXml = 'text/xml';
    case ImageSvgXml = 'image/svg+xml';
    case MultipartFormData = 'multipart/form-data';
    case ApplicationPdf = 'application/pdf';
    case ApplicationOctetStream = 'application/octet-stream';
    case ImagePng = 'image/png';
    case ImageJpeg = 'image/jpeg';
    case ImageGif = 'image/gif';
    case ApplicationXWwwFormUrlencoded = 'application/x-www-form-urlencoded';
}
