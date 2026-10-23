<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class RefSiblingDialectProfileTest extends TestCase
{
    private const string EXTERNAL_URI = 'https://api.example.com/v1/external.yaml';

    private const string SPEC_TEMPLATE = <<<'YAML'
        openapi: %s
        info:
          title: Ref Sibling Dialect API
          version: 1.0.0
        paths: {}
        components:
          schemas:
            StringTarget:
              type: string
            IntegerTarget:
              type: integer
            ObjectTarget:
              type: object
            NullableStringChainCarrier:
              $ref: '#/components/schemas/StringTarget'
              nullable: true
            ChainProperty:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/NullableStringChainCarrier'
            RefStringNullable:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/StringTarget'
                  nullable: true
            RefString:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/StringTarget'
            RefIntegerNullable:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/IntegerTarget'
                  nullable: true
            RefInteger:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/IntegerTarget'
            RefObjectNullable:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/ObjectTarget'
                  nullable: true
            RefObject:
              type: object
              properties:
                a:
                  $ref: '#/components/schemas/ObjectTarget'
        YAML;

    private const string REGISTERED_MAIN_SPEC_TEMPLATE = <<<'YAML'
        openapi: %s
        info:
          title: Registered Chain API
          version: 1.0.0
        paths: {}
        components:
          schemas:
            Client:
              type: object
              properties:
                a:
                  $ref: 'https://api.example.com/v1/external.yaml#/components/schemas/Carrier'
        YAML;

    private const string EXTERNAL_SPEC_TEMPLATE = <<<'YAML'
        openapi: %s
        info:
          title: External Chain API
          version: 1.0.0
        paths: {}
        components:
          schemas:
            Target:
              type: string
            Carrier:
              $ref: '#/components/schemas/Target'
              nullable: true
        YAML;

    #[Test]
    public function openapi_30_rejects_null_for_nullable_ref_sibling(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/RefStringNullable');
    }

    #[Test]
    public function openapi_31_accepts_null_for_nullable_ref_sibling(): void
    {
        $this->validator('3.1.0')->validateSchema(['a' => null], '#/components/schemas/RefStringNullable');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_30_rejects_null_for_nullable_sibling_on_chain_carrier(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/ChainProperty');
    }

    #[Test]
    public function openapi_31_accepts_null_for_nullable_sibling_on_chain_carrier(): void
    {
        $this->validator('3.1.0')->validateSchema(['a' => null], '#/components/schemas/ChainProperty');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_30_rejects_null_for_nullable_sibling_on_registered_document_chain_carrier(): void
    {
        $this->expectException(ValidationException::class);

        $this->registeredDocumentValidator('3.0.3')->validateSchema(['a' => null], '#/components/schemas/Client');
    }

    #[Test]
    public function openapi_31_accepts_null_for_nullable_sibling_on_registered_document_chain_carrier(): void
    {
        $this->registeredDocumentValidator('3.1.0')->validateSchema(['a' => null], '#/components/schemas/Client');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('refSiblingMatrixProvider')]
    public function property_ref_sibling_matrix(string $openapiVersion, string $schemaName, bool $nullAccepted): void
    {
        $validator = $this->validator($openapiVersion);

        if (false === $nullAccepted) {
            $this->expectException(ValidationException::class);
        }

        $validator->validateSchema(['a' => null], '#/components/schemas/' . $schemaName);

        if ($nullAccepted) {
            $this->expectNotToPerformAssertions();
        }
    }

    /** @return array<string, array{string, string, bool}> */
    public static function refSiblingMatrixProvider(): array
    {
        return [
            '3.0 nullable sibling string target rejects null' => ['3.0.3', 'RefStringNullable', false],
            '3.0 nullable sibling integer target rejects null' => ['3.0.3', 'RefIntegerNullable', false],
            '3.0 nullable sibling object target rejects null' => ['3.0.3', 'RefObjectNullable', false],
            '3.0 bare ref string target rejects null' => ['3.0.3', 'RefString', false],
            '3.0 bare ref integer target rejects null' => ['3.0.3', 'RefInteger', false],
            '3.0 bare ref object target rejects null' => ['3.0.3', 'RefObject', false],
            '3.1 nullable sibling string target accepts null' => ['3.1.0', 'RefStringNullable', true],
            '3.1 nullable sibling integer target accepts null' => ['3.1.0', 'RefIntegerNullable', true],
            '3.1 nullable sibling object target accepts null' => ['3.1.0', 'RefObjectNullable', true],
            '3.1 bare ref string target rejects null' => ['3.1.0', 'RefString', false],
            '3.1 bare ref integer target rejects null' => ['3.1.0', 'RefInteger', false],
            '3.1 bare ref object target rejects null' => ['3.1.0', 'RefObject', false],
        ];
    }

    private function validator(string $openapiVersion): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(sprintf(self::SPEC_TEMPLATE, $openapiVersion))
            ->build();
    }

    private function registeredDocumentValidator(string $openapiVersion): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, sprintf(self::EXTERNAL_SPEC_TEMPLATE, $openapiVersion))
            ->fromYamlString(sprintf(self::REGISTERED_MAIN_SPEC_TEMPLATE, $openapiVersion))
            ->build();
    }
}
