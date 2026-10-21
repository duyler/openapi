<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\Internal\SpecSizeGuard;
use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Override;

use function mb_check_encoding;

use const JSON_THROW_ON_ERROR;

final class JsonParser extends OpenApiBuilder
{
    public const int DEFAULT_MAX_SPEC_BYTES = SpecSizeGuard::DEFAULT_MAX_SPEC_BYTES;

    private readonly SpecSizeGuard $specSizeGuard;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
        int $maxSpecDepth = SpecSizeGuard::DEFAULT_MAX_SPEC_DEPTH,
        int $maxSpecBytes = SpecSizeGuard::DEFAULT_MAX_SPEC_BYTES,
    ) {
        parent::__construct($deprecationLogger);
        $this->specSizeGuard = new SpecSizeGuard($maxSpecBytes, $maxSpecDepth);
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        $this->specSizeGuard->assertSize($content);

        if (false === mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidUtf8Exception('JSON spec contains invalid UTF-8 sequences');
        }

        /** @var mixed $data */
        $data = json_decode($content, true, JsonDepthLimit::Trusted->value, JSON_THROW_ON_ERROR);

        $this->specSizeGuard->assertDepth($data);

        return $data;
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'JSON';
    }
}
