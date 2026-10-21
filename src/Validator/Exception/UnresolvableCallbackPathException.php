<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class UnresolvableCallbackPathException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(string $expression)
    {
        parent::__construct(
            sprintf(
                'Callback expression "%s" uses a runtime template that cannot be resolved; '
                . 'path validation would be a wildcard. '
                . 'Call OpenApiValidatorBuilder::disableStrictCallbackRuntimeTemplate() '
                . 'only if the application validates callback URLs through other means.',
                $expression,
            ),
        );
    }
}
