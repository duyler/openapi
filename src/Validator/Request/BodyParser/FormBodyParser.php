<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\QueryParser;

final readonly class FormBodyParser
{
    public function __construct(
        private QueryParser $queryParser,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function parse(string $body): array
    {
        if ('' === trim($body)) {
            return [];
        }

        return $this->queryParser->parse($body);
    }
}
