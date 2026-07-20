<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Override;

use function is_array;

use const JSON_THROW_ON_ERROR;

final class JsonParser extends OpenApiBuilder
{
    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
        private readonly int $maxSpecDepth = YamlParser::DEFAULT_MAX_SPEC_DEPTH,
    ) {
        parent::__construct($deprecationLogger);
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        if (false === mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidUtf8Exception('JSON spec contains invalid UTF-8 sequences');
        }

        /** @var mixed $data */
        $data = json_decode($content, true, JsonDepthLimit::Trusted->value, JSON_THROW_ON_ERROR);

        if (is_array($data)) {
            $depth = $this->calculateDepth($data);
            if ($depth > $this->maxSpecDepth) {
                throw SpecTooLargeException::forDepth($depth, $this->maxSpecDepth);
            }
        }

        return $data;
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'JSON';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function calculateDepth(array $data, int $current = 0): int
    {
        $maxChildDepth = $current;

        foreach ($data as $value) {
            /** @var mixed $value */
            if (!is_array($value)) {
                continue;
            }

            $childDepth = $this->calculateDepth($value, $current + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }
}
