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
            'Security scheme "%s" uses unsupported type "%s". duyler/openapi validates apiKey and http/bearer only; '
            . 'oauth2, openIdConnect, http/basic, http/digest, and mutualTLS require an external token validator.',
            $schemeName,
            $descriptor,
        ));
    }
}
