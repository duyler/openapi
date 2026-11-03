<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\FormatAssertionMode;

use function count;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class SuiteValidatorFactory
{
    private const string SUBJECT_SCHEMA_REF = '#/components/schemas/Subject';

    /**
     * @var array<string, FormatAssertionMode>
     */
    private const array SECTION_FORMAT_ASSERTION_MODES = [
        'format.json' => FormatAssertionMode::Annotation,
    ];

    /**
     * @var list<string>
     */
    private const array SUITE_UNKNOWN_KEYWORDS = [
        'array_of_schemas',
        'object_of_schemas',
        'unknown-keyword',
        'unknown/keyword',
    ];

    /** @var array<string, OpenApiValidatorInterface> */
    private static array $validators = [];

    private static ?OpenApiValidatorBuilder $remotesBuilder = null;

    public static function subjectSchemaRef(): string
    {
        return self::SUBJECT_SCHEMA_REF;
    }

    public static function forSchema(mixed $schema, ?string $section = null): OpenApiValidatorInterface
    {
        $formatAssertionMode = null === $section ? null : self::SECTION_FORMAT_ASSERTION_MODES[$section] ?? null;
        $cacheKey = sprintf(
            '%s|%s',
            json_encode($schema, JSON_THROW_ON_ERROR),
            $formatAssertionMode?->name ?? FormatAssertionMode::Assertion->name,
        );

        return self::$validators[$cacheKey] ??= self::build($schema, $formatAssertionMode);
    }

    public static function remotesCount(): int
    {
        return count(SuiteRemoteDocuments::all());
    }

    private static function build(mixed $schema, ?FormatAssertionMode $formatAssertionMode): OpenApiValidatorInterface
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

        $builder = self::builderWithRemotes()
            ->withExternalRefAllowedRoot(JsonSchemaSuiteLocator::remotesDirectory())
            ->withAllowedUnknownKeywords(...self::SUITE_UNKNOWN_KEYWORDS);

        if (null !== $formatAssertionMode) {
            $builder = $builder->withFormatAssertionMode($formatAssertionMode);
        }

        return $builder
            ->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))
            ->build();
    }

    private static function builderWithRemotes(): OpenApiValidatorBuilder
    {
        return self::$remotesBuilder ??= SuiteRemoteDocuments::register(
            OpenApiValidatorBuilder::create(),
        );
    }
}
