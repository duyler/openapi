<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class UnsupportedSecuritySchemeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $schemeName,
        public readonly string $schemeType,
        public readonly ?string $httpScheme = null,
    ) {
        $descriptor = null === $httpScheme ? $schemeType : sprintf('%s/%s', $schemeType, $httpScheme);

        parent::__construct(sprintf(
            'Security scheme "%s" uses unsupported type "%s": no built-in handler for the scheme type/subscheme; '
            . 'register a custom security scheme handler to validate it.',
            $schemeName,
            $descriptor,
        ));
    }
}
