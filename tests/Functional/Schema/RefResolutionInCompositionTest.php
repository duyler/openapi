<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;

/**
 * Covers $ref resolution inside allOf/oneOf/anyOf subschemas.
 *
 * Before bugfix-from-testing/10, stateless composition validators created
 * a stateless SchemaValidator without document context, so subschemas that
 * used $ref silently passed — constraints from the referenced schema were
 * never enforced. These tests verify that $ref is now pre-resolved in
 * composition arrays so that the stateless validators see real constraints.
 *
 * @internal
 */
#[CoversClass(OpenApiValidatorBuilder::class)]
final class RefResolutionInCompositionTest extends TestCase
{
    private const string ALLOF_WITH_REF_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: ref-allof
  version: 1.0.0
paths: {}
components:
  schemas:
    Base:
      type: object
      required: [id, name]
      properties:
        id:
          type: integer
          minimum: 1
        name:
          type: string
          minLength: 3
    Extended:
      allOf:
        - $ref: '#/components/schemas/Base'
        - type: object
          required: [active]
          properties:
            active:
              type: boolean
YAML;

    private const string ONEOF_WITH_REF_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: ref-oneof
  version: 1.0.0
paths: {}
components:
  schemas:
    StringValue:
      type: string
      minLength: 3
    NumberValue:
      type: number
      minimum: 10
    OneOfValue:
      oneOf:
        - $ref: '#/components/schemas/StringValue'
        - $ref: '#/components/schemas/NumberValue'
YAML;

    private const string ANYOF_WITH_REF_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: ref-anyof
  version: 1.0.0
paths: {}
components:
  schemas:
    MinLengthConstraint:
      type: string
      minLength: 5
    PatternConstraint:
      type: string
      pattern: '^[a-z]+$'
    AnyOfString:
      anyOf:
        - $ref: '#/components/schemas/MinLengthConstraint'
        - $ref: '#/components/schemas/PatternConstraint'
YAML;

    private const string NESTED_ALLOF_IN_ONEOF_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: nested-ref-composition
  version: 1.0.0
paths: {}
components:
  schemas:
    BaseShape:
      type: object
      required: [color]
      properties:
        color:
          type: string
          enum: [red, green, blue]
    Circle:
      allOf:
        - $ref: '#/components/schemas/BaseShape'
        - type: object
          required: [radius]
          properties:
            radius:
              type: number
              minimum: 0
    Square:
      allOf:
        - $ref: '#/components/schemas/BaseShape'
        - type: object
          required: [side]
          properties:
            side:
              type: number
              minimum: 0
    Shape:
      oneOf:
        - $ref: '#/components/schemas/Circle'
        - $ref: '#/components/schemas/Square'
YAML;

    private const string DISCRIMINATOR_MAPPING_WITH_REF_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: discriminator-mapping
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required: [petType, meow]
      properties:
        petType:
          type: string
        meow:
          type: boolean
    Dog:
      type: object
      required: [petType, bark]
      properties:
        petType:
          type: string
        bark:
          type: boolean
YAML;

    #[Test]
    public function ref_in_allof_subschema_constraints_applied_for_valid_data(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALLOF_WITH_REF_SPEC)
            ->build();

        $validData = [
            'id' => 42,
            'name' => 'Fluffy',
            'active' => true,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($validData, '#/components/schemas/Extended');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected Extended (allOf with $ref Base) to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_allof_subschema_invalid_minimum_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALLOF_WITH_REF_SPEC)
            ->build();

        $invalidData = [
            'id' => 0,
            'name' => 'Fluffy',
            'active' => true,
        ];

        $caught = null;

        try {
            $validator->validateSchema($invalidData, '#/components/schemas/Extended');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'id=0 (below minimum:1) from $ref Base must be rejected');

        $foundMinimum = false;
        $errors = $caught->getErrors();

        foreach ($errors as $error) {
            if ($error instanceof MinimumError) {
                $foundMinimum = true;

                break;
            }
        }

        self::assertTrue($foundMinimum, 'Expected MinimumError from Base constraint applied via $ref in allOf');
    }

    #[Test]
    public function ref_in_allof_subschema_invalid_min_length_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALLOF_WITH_REF_SPEC)
            ->build();

        $invalidData = [
            'id' => 5,
            'name' => 'ab',
            'active' => true,
        ];

        $caught = null;

        try {
            $validator->validateSchema($invalidData, '#/components/schemas/Extended');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'name="ab" (below minLength:3) from $ref Base must be rejected');

        $foundMinLength = false;
        $errors = $caught->getErrors();

        foreach ($errors as $error) {
            if ($error instanceof MinLengthError) {
                $foundMinLength = true;

                break;
            }
        }

        self::assertTrue($foundMinLength, 'Expected MinLengthError from Base constraint applied via $ref in allOf');
    }

    #[Test]
    public function ref_in_allof_subschema_missing_required_property_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALLOF_WITH_REF_SPEC)
            ->build();

        $missingNameData = [
            'id' => 5,
            'active' => true,
        ];

        $caught = null;

        try {
            $validator->validateSchema($missingNameData, '#/components/schemas/Extended');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Missing "name" from $ref Base must be rejected');

        $errors = $caught->getErrors();
        self::assertGreaterThan(0, count($errors));

        $foundRequiredName = false;

        foreach ($errors as $error) {
            if ($error instanceof RequiredError && 'name' === $error->params()['property']) {
                $foundRequiredName = true;

                break;
            }
        }

        self::assertTrue($foundRequiredName, 'Expected RequiredError for "name" from Base applied via $ref in allOf');
    }

    #[Test]
    public function ref_in_oneof_subschema_string_value_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ONEOF_WITH_REF_SPEC)
            ->build();

        $succeeded = false;

        try {
            $validator->validateSchema('hello', '#/components/schemas/OneOfValue');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected string "hello" to pass via $ref StringValue in oneOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_oneof_subschema_number_value_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ONEOF_WITH_REF_SPEC)
            ->build();

        $succeeded = false;

        try {
            $validator->validateSchema(42, '#/components/schemas/OneOfValue');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected number 42 to pass via $ref NumberValue in oneOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_oneof_subschema_invalid_value_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ONEOF_WITH_REF_SPEC)
            ->build();

        // "ab" fails minLength:3 of StringValue AND 5 fails minimum:10 of NumberValue.
        // Without bugfix-10, both $ref subschemas would pass trivially → OneOfError.
        // With bugfix-10, both fail → ValidationException (none matched).
        $caught = null;

        try {
            $validator->validateSchema('ab', '#/components/schemas/OneOfValue');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, '"ab" must be rejected: too short for StringValue and not a number for NumberValue');
    }

    #[Test]
    public function ref_in_anyof_subschema_matching_both_constraints_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ANYOF_WITH_REF_SPEC)
            ->build();

        $succeeded = false;

        try {
            $validator->validateSchema('abcdef', '#/components/schemas/AnyOfString');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected "abcdef" to pass via $ref constraints in anyOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_anyof_subschema_matching_one_constraint_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ANYOF_WITH_REF_SPEC)
            ->build();

        // "abc" fails minLength:5 but matches pattern ^[a-z]+$ → passes anyOf.
        $succeeded = false;

        try {
            $validator->validateSchema('abc', '#/components/schemas/AnyOfString');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected "abc" to pass via PatternConstraint in anyOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_anyof_subschema_invalid_value_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ANYOF_WITH_REF_SPEC)
            ->build();

        // 123 fails both MinLengthConstraint (not string) and PatternConstraint (not string).
        $caught = null;

        try {
            $validator->validateSchema(123, '#/components/schemas/AnyOfString');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Integer 123 must be rejected: does not match any $ref constraint in anyOf');
    }

    #[Test]
    public function ref_in_nested_allof_within_oneof_circle_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_ALLOF_IN_ONEOF_SPEC)
            ->build();

        $circleData = [
            'color' => 'red',
            'radius' => 5.0,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($circleData, '#/components/schemas/Shape');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected Circle (allOf with $ref BaseShape) to pass via oneOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_nested_allof_within_oneof_square_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_ALLOF_IN_ONEOF_SPEC)
            ->build();

        $squareData = [
            'color' => 'green',
            'side' => 3.5,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($squareData, '#/components/schemas/Shape');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected Square (allOf with $ref BaseShape) to pass via oneOf, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function ref_in_nested_allof_within_oneof_invalid_enum_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_ALLOF_IN_ONEOF_SPEC)
            ->build();

        // "yellow" is not in enum [red, green, blue] from BaseShape → neither Circle nor Square matches.
        $invalidData = [
            'color' => 'yellow',
            'radius' => 5.0,
        ];

        $caught = null;

        try {
            $validator->validateSchema($invalidData, '#/components/schemas/Shape');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'color="yellow" must be rejected via $ref BaseShape enum constraint in nested composition',
        );
    }

    #[Test]
    public function ref_in_nested_allof_within_oneof_missing_base_required_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_ALLOF_IN_ONEOF_SPEC)
            ->build();

        // Missing "color" from BaseShape → neither Circle nor Square allOf matches.
        $missingColorData = [
            'radius' => 5.0,
        ];

        $caught = null;

        try {
            $validator->validateSchema($missingColorData, '#/components/schemas/Shape');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Missing "color" from $ref BaseShape must be rejected in nested composition',
        );
    }

    #[Test]
    public function discriminator_mapping_with_ref_target_cat_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_MAPPING_WITH_REF_SPEC)
            ->build();

        $catData = [
            'petType' => 'cat',
            'meow' => true,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($catData, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected cat to pass via discriminator mapping → $ref Cat, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function discriminator_mapping_with_ref_target_dog_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_MAPPING_WITH_REF_SPEC)
            ->build();

        $dogData = [
            'petType' => 'dog',
            'bark' => true,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($dogData, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected dog to pass via discriminator mapping → $ref Dog, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function discriminator_mapping_with_ref_target_missing_required_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_MAPPING_WITH_REF_SPEC)
            ->build();

        // petType=cat routes via mapping to Cat schema → Cat requires "meow".
        $catWithoutMeow = [
            'petType' => 'cat',
        ];

        $caught = null;

        try {
            $validator->validateSchema($catWithoutMeow, '#/components/schemas/Pet');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Cat selected via discriminator mapping must enforce Cat required properties');

        $errors = $caught->getErrors();
        self::assertGreaterThan(0, count($errors));

        $foundRequiredMeow = false;

        foreach ($errors as $error) {
            if ($error instanceof RequiredError && 'meow' === $error->params()['property']) {
                $foundRequiredMeow = true;

                break;
            }
        }

        self::assertTrue(
            $foundRequiredMeow,
            'Expected RequiredError for "meow" from Cat schema selected via discriminator mapping',
        );
    }
}
