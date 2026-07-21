<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

use function sprintf;

/**
 * Thrown when a callback expression uses a runtime template (e.g.
 * `{$request.body#/callback_url}`) and strict callback runtime template
 * resolution is enabled.
 *
 * Strict mode is the default since the SEC-09 fix: the builder's
 * `?? true` fallback in `OpenApiValidatorBuilder::build()` enables it
 * unless {@see OpenApiValidatorBuilder::disableStrictCallbackRuntimeTemplate()}
 * is called. The legacy {@see OpenApiValidatorBuilder::enableStrictCallbackRuntimeTemplate()}
 * method is retained as a deprecated no-op.
 */
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
