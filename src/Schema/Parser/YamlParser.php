<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Override;
use Symfony\Component\Yaml\Yaml;

use function is_array;
use function strlen;

final class YamlParser extends OpenApiBuilder
{
    public const int DEFAULT_MAX_SPEC_BYTES = 1_048_576;

    public const int DEFAULT_MAX_SPEC_DEPTH = 100;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
        private readonly int $maxSpecBytes = self::DEFAULT_MAX_SPEC_BYTES,
        private readonly int $maxSpecDepth = self::DEFAULT_MAX_SPEC_DEPTH,
    ) {
        parent::__construct($deprecationLogger);
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        if (strlen($content) > $this->maxSpecBytes) {
            throw SpecTooLargeException::forSize($this->maxSpecBytes, strlen($content));
        }

        /** @var mixed $data */
        $data = Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

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
        return 'YAML';
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
