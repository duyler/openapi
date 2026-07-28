<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** @internal */
final readonly class ValidatorOptions
{
    public function __construct(
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly bool $reportDeprecated = false,
        public readonly LoggerInterface $logger = new NullLogger(),
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        public readonly bool $strictFormats = false,
        public readonly bool $strictStreaming = false,
        public readonly bool $strictCoercion = true,
        public readonly ?LoggerInterface $securityVerboseLogger = null,
    ) {}

    public function toValidatorConfiguration(): ValidatorConfiguration
    {
        return new ValidatorConfiguration(
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            reportDeprecated: $this->reportDeprecated,
            strictFormats: $this->strictFormats,
            maxJsonBodyBytes: ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES,
            maxMultipartBodyBytes: ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES,
            strictStreaming: $this->strictStreaming,
            maxRegexBacktracks: ValidatorConfiguration::DEFAULT_MAX_REGEX_BACKTRACKS,
            strictCoercion: $this->strictCoercion,
        );
    }
}
