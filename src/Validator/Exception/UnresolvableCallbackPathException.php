<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

use function sprintf;

/**
 * Thrown when a callback expression uses a runtime template (e.g.
 * `{$request.body#/callback_url}`) and strict callback runtime template
 * resolution is enabled via {@see OpenApiValidatorBuilder::enableStrictCallbackRuntimeTemplate()}.
 *
 * Runtime templates reference the original triggering request body and cannot
 * be resolved by the validator, which would otherwise make path validation a
 * wildcard that accepts any URL. Strict mode rejects such expressions instead
 * of silently accepting them.
 */
final class UnresolvableCallbackPathException extends RuntimeException
{
    public function __construct(string $expression)
    {
        parent::__construct(
            sprintf(
                'Callback expression "%s" uses a runtime template that cannot be resolved; '
                . 'path validation would be a wildcard. '
                . 'Disable strict callback runtime template mode to accept it as a wildcard.',
                $expression,
            ),
        );
    }
}
