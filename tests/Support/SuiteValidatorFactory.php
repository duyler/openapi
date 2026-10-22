<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SuiteValidatorFactory
{
    private const string SUBJECT_SCHEMA_REF = '#/components/schemas/Subject';

    /** @var array<string, OpenApiValidatorInterface> */
    private static array $validators = [];

    public static function subjectSchemaRef(): string
    {
        return self::SUBJECT_SCHEMA_REF;
    }

    public static function forSchema(mixed $schema): OpenApiValidatorInterface
    {
        $cacheKey = json_encode($schema, JSON_THROW_ON_ERROR);

        return self::$validators[$cacheKey] ??= self::build($schema);
    }

    private static function build(mixed $schema): OpenApiValidatorInterface
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'JSON Schema Test Suite',
                'version' => '1.0.0',
            ],
            'components' => [
                'schemas' => [
                    'Subject' => $schema,
                ],
            ],
        ];

        return OpenApiValidatorBuilder::create()
            ->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))
            ->withExternalRefAllowedRoot(JsonSchemaSuiteLocator::remotesDirectory())
            ->build();
    }
}
