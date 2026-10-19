<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OAS 3.0 has no way to attach `nullable` to a `$ref`, so the documented workaround is to
 * wrap the reference in a single-branch composition and put `nullable: true` beside it.
 * The keyword sits on the parent, not on the branch, and must be honored there.
 *
 * @internal
 */
final class NullableCompositionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
        openapi: 3.0.3
        info:
          title: Nullable composition
          version: 1.0.0
        components:
          schemas:
            FormIdentifier:
              type: object
              required: [type, id]
              properties:
                type:
                  enum: [forms]
                id:
                  type: string
            AllOfNullable:
              allOf:
                - $ref: '#/components/schemas/FormIdentifier'
              nullable: true
            TypedAllOfNullable:
              type: object
              allOf:
                - $ref: '#/components/schemas/FormIdentifier'
              nullable: true
            AnyOfNullable:
              anyOf:
                - $ref: '#/components/schemas/FormIdentifier'
              nullable: true
            OneOfNullable:
              oneOf:
                - $ref: '#/components/schemas/FormIdentifier'
              nullable: true
            AllOfNotNullable:
              allOf:
                - $ref: '#/components/schemas/FormIdentifier'
            AllOfBranchNullable:
              allOf:
                - type: object
                  nullable: true
                  required: [type, id]
                  properties:
                    type:
                      enum: [forms]
                    id:
                      type: string
            PlainNullable:
              type: object
              nullable: true
              required: [type, id]
              properties:
                type:
                  enum: [forms]
                id:
                  type: string
        YAML;

    private const string SPEC_31 = <<<'YAML'
        openapi: 3.1.0
        info:
          title: Nullable composition 3.1
          version: 1.0.0
        components:
          schemas:
            Identifier:
              type: object
              required: [id]
              properties:
                id:
                  type: string
            UnionOutside:
              type: ['object', 'null']
              required: [id]
              properties:
                id:
                  type: string
            UnionOnBranch:
              allOf:
                - type: ['object', 'null']
                  required: [id]
                  properties:
                    id:
                      type: string
            UnionOnParent:
              type: ['object', 'null']
              allOf:
                - $ref: '#/components/schemas/Identifier'
            NullableOnParent:
              nullable: true
              allOf:
                - $ref: '#/components/schemas/Identifier'
        YAML;

    private const string SPEC_IN_PLACE = <<<'YAML'
        openapi: 3.0.3
        info:
          title: Nullable in-place applicators
          version: 1.0.0
        components:
          schemas:
            Identifier:
              type: object
              required: [id]
              properties:
                id:
                  type: string
            IfElseNullable:
              nullable: true
              if:
                type: string
              else:
                $ref: '#/components/schemas/Identifier'
            NotNullable:
              nullable: true
              not:
                type: string
            DependentSchemasNullable:
              nullable: true
              dependentSchemas:
                a:
                  required: [b]
        YAML;

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nullableParentSchemas(): iterable
    {
        yield 'allOf + nullable' => ['AllOfNullable'];
        yield 'type + allOf + nullable' => ['TypedAllOfNullable'];
        yield 'anyOf + nullable' => ['AnyOfNullable'];
        yield 'oneOf + nullable' => ['OneOfNullable'];
        yield 'nullable branch inside allOf' => ['AllOfBranchNullable'];
        yield 'nullable without composition' => ['PlainNullable'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allSchemas(): iterable
    {
        yield from self::nullableParentSchemas();
        yield 'allOf without nullable' => ['AllOfNotNullable'];
    }

    #[Test]
    #[DataProvider('nullableParentSchemas')]
    public function null_is_accepted_by_nullable_schema(string $schemaName): void
    {
        $this->validator()->validateSchema(null, '#/components/schemas/' . $schemaName);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('allSchemas')]
    public function valid_object_is_accepted(string $schemaName): void
    {
        $this->validator()->validateSchema(self::validObject(), '#/components/schemas/' . $schemaName);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function null_is_rejected_when_composition_is_not_nullable(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateSchema(null, '#/components/schemas/AllOfNotNullable');
    }

    #[Test]
    #[DataProvider('allSchemas')]
    public function invalid_object_is_still_rejected(string $schemaName): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateSchema(['type' => 'wrong'], '#/components/schemas/' . $schemaName);
    }

    #[Test]
    #[DataProvider('nullableParentSchemas')]
    public function null_is_rejected_when_nullable_as_type_is_disabled(string $schemaName): void
    {
        $this->expectException(ValidationException::class);

        $this->validator(nullableAsType: false)->validateSchema(null, '#/components/schemas/' . $schemaName);
    }

    #[Test]
    public function oneOf_still_rejects_data_matching_more_than_one_branch(): void
    {
        $yaml = <<<'YAML'
            openapi: 3.0.3
            info:
              title: Nullable oneOf overlap
              version: 1.0.0
            components:
              schemas:
                Overlapping:
                  nullable: true
                  oneOf:
                    - type: object
                      required: [id]
                      properties:
                        id:
                          type: string
                    - type: object
                      required: [id]
                      properties:
                        id:
                          type: string
            YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($yaml)->build();

        $validator->validateSchema(null, '#/components/schemas/Overlapping');

        $this->expectException(ValidationException::class);

        $validator->validateSchema(['id' => '1'], '#/components/schemas/Overlapping');
    }

    #[Test]
    public function oas_31_null_type_union_is_honored_inside_and_outside_compositions(): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_31)->build();

        foreach (['UnionOutside', 'UnionOnBranch', 'NullableOnParent'] as $schemaName) {
            $validator->validateSchema(null, '#/components/schemas/' . $schemaName);
            $validator->validateSchema(['id' => '1'], '#/components/schemas/' . $schemaName);
        }

        $this->expectNotToPerformAssertions();
    }

    /**
     * A `null` member of a `type` union is ordinary JSON Schema and keeps composing: `allOf`
     * branches still have to match. Only OAS `nullable: true` — which the spec defines as
     * "allows sending a null value for the defined schema" — waives them.
     */
    #[Test]
    public function oas_31_null_type_union_on_parent_does_not_waive_composition_branches(): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_31)->build();

        $validator->validateSchema(['id' => '1'], '#/components/schemas/UnionOnParent');

        $this->expectException(ValidationException::class);

        $validator->validateSchema(null, '#/components/schemas/UnionOnParent');
    }

    #[Test]
    public function discriminated_oneOf_with_nullable_parent_accepts_null_and_still_discriminates(): void
    {
        $yaml = <<<'YAML'
            openapi: 3.0.3
            info:
              title: Nullable discriminated oneOf
              version: 1.0.0
            components:
              schemas:
                Cat:
                  type: object
                  required: [petType, meows]
                  properties:
                    petType:
                      type: string
                    meows:
                      type: boolean
                Dog:
                  type: object
                  required: [petType, barks]
                  properties:
                    petType:
                      type: string
                    barks:
                      type: boolean
                Pet:
                  nullable: true
                  discriminator:
                    propertyName: petType
                    mapping:
                      cat: '#/components/schemas/Cat'
                      dog: '#/components/schemas/Dog'
                  oneOf:
                    - $ref: '#/components/schemas/Cat'
                    - $ref: '#/components/schemas/Dog'
            YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($yaml)->build();

        $validator->validateSchema(null, '#/components/schemas/Pet');
        $validator->validateSchema(['petType' => 'cat', 'meows' => true], '#/components/schemas/Pet');

        $this->expectException(ValidationException::class);

        $validator->validateSchema(['petType' => 'cat', 'barks' => true], '#/components/schemas/Pet');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function inPlaceApplicators(): iterable
    {
        yield 'if/else' => ['IfElseNullable'];
        yield 'not' => ['NotNullable'];
        yield 'dependentSchemas' => ['DependentSchemasNullable'];
    }

    #[Test]
    #[DataProvider('inPlaceApplicators')]
    public function nullable_parent_accepts_null_for_other_in_place_applicators(string $schemaName): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_IN_PLACE)->build();

        $validator->validateSchema(null, '#/components/schemas/' . $schemaName);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_parent_does_not_stop_if_else_from_routing_non_null_data(): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_IN_PLACE)->build();

        $validator->validateSchema('a string', '#/components/schemas/IfElseNullable');
        $validator->validateSchema(['id' => '1'], '#/components/schemas/IfElseNullable');

        $this->expectException(ValidationException::class);

        $validator->validateSchema(['id' => 1], '#/components/schemas/IfElseNullable');
    }

    /**
     * @return array{type: string, id: string}
     */
    private static function validObject(): array
    {
        return ['type' => 'forms', 'id' => '1'];
    }

    private function validator(bool $nullableAsType = true): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC);

        $builder = $nullableAsType
            ? $builder->enableNullableAsType()
            : $builder->disableNullableAsType();

        return $builder->build();
    }
}
