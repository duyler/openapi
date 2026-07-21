<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format;

use Duyler\OpenApi\Validator\Format\Numeric\FloatDoubleValidator;
use Duyler\OpenApi\Validator\Format\Numeric\IntegerRangeValidator;
use Duyler\OpenApi\Validator\Format\String\BinaryValidator;
use Duyler\OpenApi\Validator\Format\String\ByteValidator;
use Duyler\OpenApi\Validator\Format\String\DateTimeValidator;
use Duyler\OpenApi\Validator\Format\String\DateValidator;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use Duyler\OpenApi\Validator\Format\String\HostnameValidator;
use Duyler\OpenApi\Validator\Format\String\IdnEmailValidator;
use Duyler\OpenApi\Validator\Format\String\IdnHostnameValidator;
use Duyler\OpenApi\Validator\Format\String\IriReferenceValidator;
use Duyler\OpenApi\Validator\Format\String\IriValidator;
use Duyler\OpenApi\Validator\Format\String\Ipv4Validator;
use Duyler\OpenApi\Validator\Format\String\Ipv6Validator;
use Duyler\OpenApi\Validator\Format\String\JsonPointerValidator;
use Duyler\OpenApi\Validator\Format\String\PasswordValidator;
use Duyler\OpenApi\Validator\Format\String\RegexFormatValidator;
use Duyler\OpenApi\Validator\Format\String\RelativeJsonPointerValidator;
use Duyler\OpenApi\Validator\Format\String\TimeValidator;
use Duyler\OpenApi\Validator\Format\String\UriReferenceValidator;
use Duyler\OpenApi\Validator\Format\String\UriTemplateValidator;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use Duyler\OpenApi\Validator\Format\String\UuidValidator;
use Duyler\OpenApi\Validator\PregExecutor;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class BuiltinFormats
{
    public static function create(PregExecutor $pregExecutor = new PregExecutor()): FormatRegistry
    {
        $registry = new FormatRegistry();

        $registry = $registry->registerFormat('string', 'date-time', new DateTimeValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'date', new DateValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'time', new TimeValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'email', new EmailValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'uri', new UriValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'uuid', new UuidValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'hostname', new HostnameValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'ipv4', new Ipv4Validator());
        $registry = $registry->registerFormat('string', 'ipv6', new Ipv6Validator());
        $registry = $registry->registerFormat('string', 'byte', new ByteValidator());

        $registry = $registry->registerFormat('number', 'float', new FloatDoubleValidator('float'));
        $registry = $registry->registerFormat('number', 'double', new FloatDoubleValidator('double'));

        $registry = $registry->registerFormat('string', 'duration', new DurationValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'json-pointer', new JsonPointerValidator($pregExecutor));
        $registry = $registry->registerFormat(
            'string',
            'relative-json-pointer',
            new RelativeJsonPointerValidator($pregExecutor),
        );

        $registry = $registry->registerFormat('integer', 'int32', new IntegerRangeValidator('int32', -2_147_483_648, 2_147_483_647));
        $registry = $registry->registerFormat('integer', 'int64', new IntegerRangeValidator('int64', PHP_INT_MIN, PHP_INT_MAX));

        $registry = $registry->registerFormat('string', 'binary', new BinaryValidator());
        $registry = $registry->registerFormat('string', 'password', new PasswordValidator());

        $registry = $registry->registerFormat('string', 'idn-email', new IdnEmailValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'idn-hostname', new IdnHostnameValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'iri', new IriValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'iri-reference', new IriReferenceValidator($pregExecutor));

        $registry = $registry->registerFormat('string', 'uri-reference', new UriReferenceValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'uri-template', new UriTemplateValidator($pregExecutor));
        $registry = $registry->registerFormat('string', 'regex', new RegexFormatValidator($pregExecutor));

        return $registry;
    }
}
