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
