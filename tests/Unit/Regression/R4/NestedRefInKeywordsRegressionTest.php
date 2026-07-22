<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function implode;
use function sprintf;

/**
 * Regression suite for R4-CORRECTNESS-001 / R4-TEST-004: `$ref`
 * resolution inside every schema-typed keyword. Anti-test: rolling
 * back the fix (returning the legacy recursion engine without
 * document context) makes the `{$ref: ...}` stub a no-op, so the
 * invalid data passes validation silently and every dataset below
 * fails to throw.
 *
 * Unlike the inline NestedRefResolutionTest, this suite drives every
 * keyword through the public {@see OpenApiValidatorBuilder::build()}
 * path so the regression also fires when the builder wiring breaks.
 *
 * @internal
 */
final class NestedRefInKeywordsRegressionTest extends TestCase
{
    private const string STRICT_SCHEMA_YAML = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Strict:
      type: object
      required: [value]
      properties:
        value: { type: integer, minimum: 0 }
      additionalProperties: false
    NameRule:
      type: string
      pattern: '^[a-z]+$'
      minLength: 1
    PositiveInt:
      type: integer
      minimum: 0
YAML;

    #[Test]
    #[DataProvider('schemaTypedKeywordProvider')]
    public function nested_ref_in_keyword_is_resolved_before_recursion(
        string $containerSpec,
        mixed $invalidData,
        string $expectedKeyword,
    ): void {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($containerSpec)
            ->build();

        $caught = null;
        try {
            $validator->validateSchema($invalidData, '#/components/schemas/Container');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Legacy no-op $ref resolution would let the invalid data pass — expected a validation failure.',
        );
        self::assertInstanceOf(ValidationException::class, $caught);

        $errors = $caught->getErrors();
        self::assertNotEmpty($errors, 'ValidationException must carry at least one typed error.');

        $keywords = array_map(static fn($error) => $error->keyword(), $errors);
        self::assertContains(
            $expectedKeyword,
            $keywords,
            sprintf(
                'Expected keyword "%s" to be raised by nested $ref in spec; got [%s].',
                $expectedKeyword,
                implode(', ', $keywords),
            ),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function schemaTypedKeywordProvider(): iterable
    {
        yield 'additionalProperties resolves $ref and applies minimum' => [
            self::containerObject(<<<'YAML'
      additionalProperties:
        $ref: '#/components/schemas/Strict'
YAML),
            ['foo' => ['value' => -5]],
            'minimum',
        ];

        yield 'patternProperties resolves $ref and applies minimum' => [
            self::containerObject(<<<'YAML'
      patternProperties:
        '^k_':
          $ref: '#/components/schemas/Strict'
YAML),
            ['k_bad' => ['value' => -1]],
            'minimum',
        ];

        yield 'unevaluatedProperties resolves $ref and applies minimum' => [
            self::containerObject(<<<'YAML'
      properties:
        known: { type: integer }
      unevaluatedProperties:
        $ref: '#/components/schemas/Strict'
YAML),
            ['extra' => ['value' => -1]],
            'minimum',
        ];

        yield 'unevaluatedItems resolves $ref and applies minimum' => [
            self::containerArray(<<<'YAML'
      prefixItems:
        - { type: integer }
      unevaluatedItems:
        $ref: '#/components/schemas/Strict'
YAML),
            [1, ['value' => -2]],
            'minimum',
        ];

        yield 'prefixItems resolves $ref and applies minimum' => [
            self::containerArray(<<<'YAML'
      prefixItems:
        - $ref: '#/components/schemas/Strict'
YAML),
            [['value' => -1]],
            'minimum',
        ];

        yield 'contains resolves $ref and rejects arrays with no matching item' => [
            self::containerArray(<<<'YAML'
      contains:
        $ref: '#/components/schemas/Strict'
YAML),
            [['value' => -3], ['value' => -4]],
            'contains',
        ];

        yield 'propertyNames resolves $ref and rejects uppercase property name' => [
            self::containerObject(<<<'YAML'
      propertyNames:
        $ref: '#/components/schemas/NameRule'
YAML),
            ['UPPER' => 1],
            'pattern',
        ];

        yield 'dependentSchemas resolves $ref when trigger property is present' => [
            self::containerObject(<<<'YAML'
      dependentSchemas:
        value:
          $ref: '#/components/schemas/Strict'
YAML),
            ['value' => -5],
            'minimum',
        ];

        yield 'not resolves $ref and rejects data that matches the referenced schema' => [
            self::containerObject(<<<'YAML'
      not:
        $ref: '#/components/schemas/PositiveInt'
YAML),
            5,
            'not',
        ];

        yield 'if/then/else resolves $ref inside then branch' => [
            self::containerObject(<<<'YAML'
      if:
        type: object
        required: [mode]
        properties:
          mode:
            type: string
            enum: [strict]
      then:
        type: object
        properties:
          payload:
            $ref: '#/components/schemas/Strict'
YAML),
            ['mode' => 'strict', 'payload' => ['value' => -2]],
            'minimum',
        ];
    }

    private static function containerObject(string $innerYaml): string
    {
        return self::STRICT_SCHEMA_YAML . "\n    Container:\n      type: object\n" . $innerYaml;
    }

    private static function containerArray(string $innerYaml): string
    {
        return self::STRICT_SCHEMA_YAML . "\n    Container:\n      type: array\n" . $innerYaml;
    }
}
