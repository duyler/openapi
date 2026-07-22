<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\PregExecutor;

final readonly class ValidatorConfiguration
{
    public const int DEFAULT_MAX_JSON_BODY_BYTES = 10_485_760;

    public const int DEFAULT_MAX_MULTIPART_BODY_BYTES = 52_428_800;

    public const int DEFAULT_MAX_REGEX_BACKTRACKS = PregExecutor::DEFAULT_MAX_BACKTRACKS;

    public const int DEFAULT_MAX_STREAMING_RECORDS = 100_000;

    public function __construct(
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly bool $securityValidation = false,
        public readonly bool $strictFormats = false,
        public readonly bool $reportDeprecated = true,
        public readonly int $maxJsonBodyBytes = self::DEFAULT_MAX_JSON_BODY_BYTES,
        public readonly int $maxMultipartBodyBytes = self::DEFAULT_MAX_MULTIPART_BODY_BYTES,
        public readonly bool $strictStreaming = false,
        public readonly int $maxRegexBacktracks = self::DEFAULT_MAX_REGEX_BACKTRACKS,
        public readonly int $maxStreamingRecords = self::DEFAULT_MAX_STREAMING_RECORDS,
        public readonly bool $strictCoercion = true,
    ) {}
}
