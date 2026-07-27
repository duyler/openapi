<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\ValidatorPool;

/** @internal */
final readonly class RootServices
{
    public function __construct(
        public readonly OpenApiDocument $document,
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly RefResolver $refResolver,
        public readonly PathRegexCache $pathRegexCache = new PathRegexCache(),
        public readonly RegexValidator $regexValidator = new RegexValidator(),
        public readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}
}
