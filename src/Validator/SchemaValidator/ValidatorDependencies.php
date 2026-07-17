<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ValidatorDependencies
{
    public function __construct(
        public ValidatorPool $pool,
        public FormatRegistry $formatRegistry,
        public bool $strictFormats = false,
        public LoggerInterface $logger = new NullLogger(),
        public bool $reportDeprecated = false,
        public ?EventDispatcherInterface $eventDispatcher = null,
        public ?ValidatorRegistryInterface $registry = null,
        public RegexValidator $regexValidator = new RegexValidator(),
        public PregExecutor $pregExecutor = new PregExecutor(),
    ) {}
}
