<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Exception\TypeArrayNotSupportedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_contains;

final class TypeArrayRejectBuildTest extends TestCase
{
    private const string TYPE_ARRAY_SPEC_TEMPLATE = <<<'YAML'
openapi: %s
info:
  title: Type Array API
  version: 1.0.0
components:
  schemas:
    NullableString:
      type:
        - string
        - 'null'
YAML;

    #[Test]
    public function build_refuses_type_array_in_openapi_30_document(): void
    {
        $this->expectException(TypeArrayNotSupportedException::class);
        $this->expectExceptionMessage('Type arrays are not allowed in OpenAPI 3.0 documents');

        $this->buildValidator('3.0.3');
    }

    #[Test]
    public function build_refuse_message_does_not_expose_spec_values(): void
    {
        try {
            $this->buildValidator('3.0.3');
            self::fail(TypeArrayNotSupportedException::class . ' was not thrown');
        } catch (TypeArrayNotSupportedException $exception) {
            $message = $exception->getMessage();

            self::assertTrue(str_contains($message, 'Type arrays are not allowed'));
            self::assertFalse(str_contains($message, "'string'"));
            self::assertFalse(str_contains($message, "'null'"));
        }
    }

    #[Test]
    public function build_accepts_type_array_in_openapi_31_document(): void
    {
        $validator = $this->buildValidator('3.1.0');

        $validator->validateSchema(null, '#/components/schemas/NullableString');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function build_accepts_string_instance_for_type_array_in_openapi_31_document(): void
    {
        $validator = $this->buildValidator('3.1.0');

        $validator->validateSchema('hello', '#/components/schemas/NullableString');

        $this->expectNotToPerformAssertions();
    }

    private function buildValidator(string $openapiVersion): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(sprintf(self::TYPE_ARRAY_SPEC_TEMPLATE, $openapiVersion))
            ->build();
    }
}
