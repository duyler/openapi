<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

final readonly class FieldMetadata
{
    public const string CATEGORY_FLAT = 'flat';
    public const string CATEGORY_STRING = 'string';
    public const string CATEGORY_NUMERIC = 'numeric';
    public const string CATEGORY_ARRAY = 'array';
    public const string CATEGORY_OBJECT = 'object';
    public const string CATEGORY_COMPOSITION = 'composition';

    public function __construct(
        public string $name,
        public string $openApiName,
        public string $category,
        public bool $hasSentinel = false,
    ) {}
}
