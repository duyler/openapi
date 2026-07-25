<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;

/**
 * @internal
 */
final readonly class ScalarFields
{
    /**
     * @param string|list<string>|null                       $type
     * @param string|int|float|bool|array|null               $default
     * @param string|int|float|bool|array|null               $const
     * @param string|int|float|bool|array|null               $example
     * @param list<mixed>|null                                $enum
     * @param array<string, mixed>|null                       $examples
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $format = null,
        public ?string $title = null,
        public ?string $description = null,
        public string|int|float|bool|array|null $default = null,
        public ?bool $hasDefault = null,
        public ?bool $deprecated = null,
        public ?bool $readOnly = null,
        public ?bool $writeOnly = null,
        public string|array|null $type = null,
        public string|int|float|bool|array|null $const = null,
        public ?bool $hasConst = null,
        public ?float $multipleOf = null,
        public ?float $maximum = null,
        public ?float $exclusiveMaximum = null,
        public ?float $minimum = null,
        public ?float $exclusiveMinimum = null,
        public ?int $maxLength = null,
        public ?int $minLength = null,
        public ?string $pattern = null,
        public string|int|float|bool|array|null $example = null,
        public ?array $examples = null,
        public ?array $enum = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public Schema|bool|null $contentSchema = null,
        public ?string $jsonSchemaDialect = null,
        public ?Discriminator $discriminator = null,
        public ?Xml $xml = null,
    ) {}
}
