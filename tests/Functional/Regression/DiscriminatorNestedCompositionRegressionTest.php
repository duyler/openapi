<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * @internal
 */
final class DiscriminatorNestedCompositionRegressionTest extends TestCase
{
    private const string NESTED_COMPOSITION_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Container:
      oneOf:
        - oneOf:
            - type: object
              required: [kind]
              properties:
                kind: { type: string, enum: [other] }
        - $ref: '#/components/schemas/Cat'
      discriminator:
        propertyName: kind
        mapping:
          other: '#/components/schemas/Other'
          cat: '#/components/schemas/Cat'
    Other:
      type: object
      required: [kind]
      properties:
        kind: { type: string, enum: [other] }
    Cat:
      type: object
      required: [kind, name]
      properties:
        kind: { type: string, enum: [cat] }
        name: { type: string }
YAML;

    private const string THREE_LEVEL_NESTED_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Root:
      oneOf:
        - oneOf:
            - type: object
              required: [species]
              properties:
                species: { type: string, enum: [unknown] }
        - $ref: '#/components/schemas/Dog'
      discriminator:
        propertyName: species
        mapping:
          unknown: '#/components/schemas/UnknownLeaf'
          dog: '#/components/schemas/Dog'
    UnknownLeaf:
      type: object
      required: [species]
      properties:
        species: { type: string, enum: [unknown] }
    Dog:
      type: object
      required: [species, bark]
      properties:
        species: { type: string, enum: [dog] }
        bark: { type: boolean }
YAML;

    private const string UNEVALUATED_PROPERTIES_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Pet:
      allOf:
        - discriminator:
            propertyName: petType
            mapping:
              cat: '#/components/schemas/Cat'
        - oneOf:
            - $ref: '#/components/schemas/Cat'
      unevaluatedProperties: false
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType: { type: string, enum: [cat] }
        name: { type: string }
        age: { type: integer }
YAML;

    private const string TARGET_ADDITIONAL_PROPERTY_REF_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Pet:
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Strict:
      type: integer
      minimum: 0
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType: { type: string, enum: [cat] }
        name: { type: string }
      additionalProperties:
        $ref: '#/components/schemas/Strict'
YAML;

    #[Test]
    public function nested_one_of_with_discriminator_resolves_to_outer_candidate(): void
    {
        $validator = $this->build(self::NESTED_COMPOSITION_SPEC);

        $validator->validateSchema(
            ['kind' => 'cat', 'name' => 'Tom'],
            '#/components/schemas/Container',
        );

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function three_level_nested_composition_resolves_when_first_chain_does_not_match(): void
    {
        $validator = $this->build(self::THREE_LEVEL_NESTED_SPEC);

        $validator->validateSchema(
            ['species' => 'dog', 'bark' => true],
            '#/components/schemas/Root',
        );

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_target_with_unevaluated_properties_false_accepts_target_fields(): void
    {
        $validator = $this->build(self::UNEVALUATED_PROPERTIES_SPEC);

        $validator->validateSchema(
            ['petType' => 'cat', 'name' => 'Tom', 'age' => 5],
            '#/components/schemas/Pet',
        );

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_target_with_unevaluated_properties_false_rejects_unknown_field(): void
    {
        $validator = $this->build(self::UNEVALUATED_PROPERTIES_SPEC);

        $this->expectException(ValidationException::class);

        $validator->validateSchema(
            ['petType' => 'cat', 'name' => 'Tom', 'rogue' => 1],
            '#/components/schemas/Pet',
        );
    }

    #[Test]
    public function discriminator_target_additional_properties_ref_combined_with_discriminator(): void
    {
        $validator = $this->build(self::TARGET_ADDITIONAL_PROPERTY_REF_SPEC);

        $validator->validateSchema(
            ['petType' => 'cat', 'name' => 'Tom', 'lives' => 9],
            '#/components/schemas/Pet',
        );

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_target_additional_properties_ref_rejects_negative_extra_field(): void
    {
        $validator = $this->build(self::TARGET_ADDITIONAL_PROPERTY_REF_SPEC);

        $caught = null;
        try {
            $validator->validateSchema(
                ['petType' => 'cat', 'name' => 'Tom', 'lives' => -1],
                '#/components/schemas/Pet',
            );
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Negative lives value must fail Strict additionalProperties minimum constraint.');
        $keywords = array_map(static fn($error) => $error->keyword(), $caught->getErrors());
        self::assertContains('minimum', $keywords);
    }

    private function build(string $spec): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();
    }
}
