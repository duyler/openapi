<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Override;
use Stringable;

use function count;
use function sprintf;

final readonly class Operation implements Stringable
{
    private readonly int $placeholderCount;

    /**
     * @param array<string, string> $pathParameters Resolved path parameter values keyed by placeholder name
     */
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly ?string $operationId = null,
        public readonly array $pathParameters = [],
        public readonly ?SchemaOperation $schemaOperation = null,
    ) {
        preg_match_all('/\{[^}]+\}/', $this->path, $matches);

        $this->placeholderCount = count($matches[0]);
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s %s', strtoupper($this->method), $this->path);
    }

    public function countPlaceholders(): int
    {
        return $this->placeholderCount;
    }
}
