<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\JsonSchemaSuite;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\FormatAssertionMode;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/** @internal */
#[CoversNothing]
final class FormatAnnotationModeSuiteTest extends TestCase
{
    /** @var array<string, OpenApiValidatorInterface> */
    private static array $validators = [];

    public static function formatAnnotationCases(): array
    {
        $file = JsonSchemaSuiteLocator::suiteFile('draft2020-12/format.json');

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        $cases = [];

        foreach ($decoded as $groupIndex => $group) {
            foreach ($group['tests'] as $testIndex => $test) {
                $cases[sprintf(
                    '%s [%d/%d] %s',
                    $group['description'],
                    $groupIndex,
                    $testIndex,
                    $test['description'],
                )] = [$group['schema'], $test['data']];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('formatAnnotationCases')]
    public function annotation_mode_matches_format_section(mixed $schema, mixed $instance): void
    {
        $validator = self::validatorFor($schema);

        try {
            $validator->validateSchema($instance, '#/components/schemas/Subject');

            $isValid = true;
        } catch (ValidationException|InvalidFormatException) {
            $isValid = false;
        }

        self::assertTrue($isValid, sprintf('Instance must satisfy the annotation-default section: %s', json_encode($instance)));
    }

    #[Test]
    public function format_section_is_fully_covered(): void
    {
        self::assertCount(133, self::formatAnnotationCases());
    }

    private static function validatorFor(mixed $schema): OpenApiValidatorInterface
    {
        $cacheKey = json_encode($schema, JSON_THROW_ON_ERROR);

        if (isset(self::$validators[$cacheKey])) {
            return self::$validators[$cacheKey];
        }

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'JSON Schema Test Suite format.json (annotation mode)',
                'version' => '1.0.0',
            ],
            'components' => [
                'schemas' => [
                    'Subject' => $schema,
                ],
            ],
        ];

        return self::$validators[$cacheKey] = OpenApiValidatorBuilder::create()
            ->withFormatAssertionMode(FormatAssertionMode::Annotation)
            ->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))
            ->build();
    }
}
