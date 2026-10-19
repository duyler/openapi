<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullableRefSiblingTest extends TestCase
{
    private const string SPEC = <<<'YAML'
        openapi: 3.0.0
        info:
          title: Nullable Ref API
          version: 1.0.0
        paths: {}
        components:
          schemas:
            NullableString:
              type: string
              nullable: true
            UnionNullString:
              type: [string, 'null']
            NonNullableString:
              type: string

            InlineProp:
              type: object
              properties:
                p:
                  type: string
                  nullable: true
            RefProp:
              type: object
              properties:
                p:
                  $ref: '#/components/schemas/NullableString'
            RefPropRestatingNullable:
              type: object
              properties:
                p:
                  $ref: '#/components/schemas/NullableString'
                  nullable: true
            RefPropTypeUnion:
              type: object
              properties:
                p:
                  $ref: '#/components/schemas/UnionNullString'
            RefPropNullableSibling:
              type: object
              properties:
                p:
                  $ref: '#/components/schemas/NonNullableString'
                  nullable: true
            RefPropNonNullable:
              type: object
              properties:
                p:
                  $ref: '#/components/schemas/NonNullableString'
        YAML;

    #[Test]
    public function inline_nullable_property_accepts_null(): void
    {
        $this->validator()->validateSchema(['p' => null], '#/components/schemas/InlineProp');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function bare_ref_to_nullable_schema_accepts_null(): void
    {
        $this->validator()->validateSchema(['p' => null], '#/components/schemas/RefProp');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ref_restating_nullable_accepts_null(): void
    {
        $this->validator()->validateSchema(['p' => null], '#/components/schemas/RefPropRestatingNullable');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ref_to_type_union_with_null_accepts_null(): void
    {
        $this->validator()->validateSchema(['p' => null], '#/components/schemas/RefPropTypeUnion');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_sibling_widens_non_nullable_target(): void
    {
        $this->validator()->validateSchema(['p' => null], '#/components/schemas/RefPropNullableSibling');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function bare_ref_to_non_nullable_schema_still_rejects_null(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateSchema(['p' => null], '#/components/schemas/RefPropNonNullable');
    }

    #[Test]
    public function bare_ref_to_nullable_schema_still_rejects_wrong_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateSchema(['p' => 42], '#/components/schemas/RefProp');
    }

    private function validator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC)->build();
    }
}
