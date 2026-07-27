<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\PregExecutor;

/** @internal */
final readonly class BodyLimits
{
    public function __construct(
        public readonly int $maxJsonBodyBytes = ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES,
        public readonly int $maxMultipartBodyBytes = ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES,
        public readonly int $maxRegexBacktracks = PregExecutor::DEFAULT_MAX_BACKTRACKS,
    ) {}
}
