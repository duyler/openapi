<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;

final readonly class ValidatorConfiguration
{
    public const int DEFAULT_MAX_JSON_BODY_BYTES = 10_485_760;

    public const int DEFAULT_MAX_MULTIPART_BODY_BYTES = 52_428_800;

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
    ) {}
}
