<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class NullableRequiresTypeDialectProfileTest extends TestCase
{
    private const string SPEC_TEMPLATE = <<<'YAML'
        openapi: %s
        info:
          title: Nullable Requires Type API
          version: 1.0.0
        paths: {}
        components:
          schemas:
            NullableNoType:
              nullable: true
            NullableString:
              type: string
              nullable: true
            PlainString:
              type: string
            ObjectWithNullableNoType:
              type: object
              properties:
                a:
                  nullable: true
            ObjectWithNullableString:
              type: object
              properties:
                a:
                  type: string
                  nullable: true
            ObjectWithPlainString:
              type: object
              properties:
                a:
                  type: string
        YAML;

    private const string SPEC_31_TYPE_ARRAY_TEMPLATE = <<<'YAML'
        openapi: 3.1.0
        info:
          title: Nullable Requires Type API (3.1 type array)
          version: 1.0.0
        paths: {}
        components:
          schemas:
            NullableTypeArray:
              type: ['string', 'null']
        YAML;

    #[Test]
    public function openapi_30_rejects_null_for_nullable_without_type(): void
    {
        $this->expectException(InvalidDataTypeException::class);

        $this->validator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/ObjectWithNullableNoType');
    }

    #[Test]
    public function openapi_30_accepts_null_for_nullable_string(): void
    {
        $this->validator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/ObjectWithNullableString');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_30_accepts_null_for_nullable_string_schema_root(): void
    {
        $this->validator('3.0.3')->validateSchema(null, '#/components/schemas/NullableString');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_30_rejects_null_for_plain_string(): void
    {
        $this->expectException(InvalidDataTypeException::class);

        $this->validator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/ObjectWithPlainString');
    }

    #[Test]
    public function openapi_31_accepts_null_for_nullable_without_type(): void
    {
        $this->validator('3.1.0')->validateSchema(['a' => null], '#/components/schemas/ObjectWithNullableNoType');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_31_accepts_null_for_nullable_without_type_schema_root(): void
    {
        $this->validator('3.1.0')->validateSchema(null, '#/components/schemas/NullableNoType');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_31_accepts_null_for_type_array_with_null_member(): void
    {
        $validator = $this->typeArrayValidator();

        $validator->validateSchema(null, '#/components/schemas/NullableTypeArray');
        $validator->validateSchema('text', '#/components/schemas/NullableTypeArray');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_31_type_array_rejects_wrong_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->typeArrayValidator()->validateSchema(1, '#/components/schemas/NullableTypeArray');
    }

    #[Test]
    #[DataProvider('nullablePropertyMatrixProvider')]
    public function nullable_property_matrix(string $openapiVersion, string $schemaName, bool $nullAccepted): void
    {
        $validator = $this->validator($openapiVersion);

        if (false === $nullAccepted) {
            $this->expectException(InvalidDataTypeException::class);
        }

        $validator->validateSchema(['a' => null], '#/components/schemas/' . $schemaName);

        if ($nullAccepted) {
            $this->expectNotToPerformAssertions();
        }
    }

    /** @return array<string, array{string, string, bool}> */
    public static function nullablePropertyMatrixProvider(): array
    {
        return [
            '3.0 nullable without type rejects null' => ['3.0.3', 'ObjectWithNullableNoType', false],
            '3.0 nullable string accepts null' => ['3.0.3', 'ObjectWithNullableString', true],
            '3.0 plain string rejects null' => ['3.0.3', 'ObjectWithPlainString', false],
            '3.1 nullable without type accepts null' => ['3.1.0', 'ObjectWithNullableNoType', true],
            '3.1 nullable string accepts null' => ['3.1.0', 'ObjectWithNullableString', true],
            '3.1 plain string rejects null' => ['3.1.0', 'ObjectWithPlainString', false],
        ];
    }

    private function validator(string $openapiVersion): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(sprintf(self::SPEC_TEMPLATE, $openapiVersion))
            ->build();
    }

    private function typeArrayValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_31_TYPE_ARRAY_TEMPLATE)
            ->build();
    }
}
