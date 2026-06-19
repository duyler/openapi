<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\SchemaValidatorAdapter;
use Duyler\OpenApi\Validator\Validation\WebhookValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

final readonly class ValidatorDependencies
{
    public function __construct(
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly LoggerInterface $logger,
        public readonly RefResolver $refResolver,
        public readonly PathFinder $pathFinder,
        public readonly LinkResolver $linkResolver,
        public readonly RequestValidationHandler $requestValidation,
        public readonly ResponseValidationHandler $responseValidation,
        public readonly SchemaValidatorAdapter $schemaValidation,
        public readonly WebhookValidator $webhookValidation,
        public readonly CallbackValidator $callbackValidation,
        public readonly PathRegexCache $pathRegexCache,
        public readonly RegexValidator $regexValidator,
        public readonly ?SchemaCache $cache = null,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}
}
