<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format;

use Duyler\OpenApi\Validator\Format\Numeric\FloatDoubleValidator;
use Duyler\OpenApi\Validator\Format\String\ByteValidator;
use Duyler\OpenApi\Validator\Format\String\DateTimeValidator;
use Duyler\OpenApi\Validator\Format\String\DateValidator;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use Duyler\OpenApi\Validator\Format\String\HostnameValidator;
use Duyler\OpenApi\Validator\Format\String\Ipv4Validator;
use Duyler\OpenApi\Validator\Format\String\Ipv6Validator;
use Duyler\OpenApi\Validator\Format\String\JsonPointerValidator;
use Duyler\OpenApi\Validator\Format\String\RelativeJsonPointerValidator;
use Duyler\OpenApi\Validator\Format\String\TimeValidator;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use Duyler\OpenApi\Validator\Format\String\UuidValidator;

readonly class BuiltinFormats
{
    public static function create(): FormatRegistry
    {
        $registry = new FormatRegistry();

        $registry = $registry->registerFormat('string', 'date-time', new DateTimeValidator());
        $registry = $registry->registerFormat('string', 'date', new DateValidator());
        $registry = $registry->registerFormat('string', 'time', new TimeValidator());
        $registry = $registry->registerFormat('string', 'email', new EmailValidator());
        $registry = $registry->registerFormat('string', 'uri', new UriValidator());
        $registry = $registry->registerFormat('string', 'uuid', new UuidValidator());
        $registry = $registry->registerFormat('string', 'hostname', new HostnameValidator());
        $registry = $registry->registerFormat('string', 'ipv4', new Ipv4Validator());
        $registry = $registry->registerFormat('string', 'ipv6', new Ipv6Validator());
        $registry = $registry->registerFormat('string', 'byte', new ByteValidator());

        $registry = $registry->registerFormat('number', 'float', new FloatDoubleValidator('float'));
        $registry = $registry->registerFormat('number', 'double', new FloatDoubleValidator('double'));

        $registry = $registry->registerFormat('string', 'duration', new DurationValidator());
        $registry = $registry->registerFormat('string', 'json-pointer', new JsonPointerValidator());
        $registry = $registry->registerFormat(
            'string',
            'relative-json-pointer',
            new RelativeJsonPointerValidator(),
        );

        return $registry;
    }
}
