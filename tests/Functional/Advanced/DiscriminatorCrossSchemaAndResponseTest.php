<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function sprintf;

final class DiscriminatorCrossSchemaAndResponseTest extends TestCase
{
    private const string PET_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Pet Discriminator API
  version: 1.0.0
paths:
  /pets/{id}:
    get:
      operationId: getPet
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: A pet
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Pet'
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
      required: [petType, name]
      additionalProperties: false
      properties:
        petType:
          type: string
          enum: [cat]
        name:
          type: string
    Dog:
      type: object
      required: [petType, name, breed]
      additionalProperties: false
      properties:
        petType:
          type: string
          enum: [dog]
        name:
          type: string
        breed:
          type: string
YAML;

    private const string REF_WITHOUT_DISCRIMINATOR_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: User Address API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      required: [name]
      additionalProperties: false
      properties:
        name:
          type: string
        address:
          $ref: '#/components/schemas/Address'
    Address:
      type: object
      required: [city]
      properties:
        city:
          type: string
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function di_04_cat_with_only_cat_fields_passes_via_validate_schema(): void
    {
        $validator = $this->buildPetValidator();

        $this->assertSchemaPasses(
            $validator,
            ['petType' => 'cat', 'name' => 'Fluffy'],
        );
    }

    #[Test]
    public function di_04_dog_with_only_dog_fields_passes_via_validate_schema(): void
    {
        $validator = $this->buildPetValidator();

        $this->assertSchemaPasses(
            $validator,
            ['petType' => 'dog', 'name' => 'Rex', 'breed' => 'Lab'],
        );
    }

    #[Test]
    public function di_04_cat_with_dog_only_field_bark_rejected_by_additional_properties(): void
    {
        $validator = $this->buildPetValidator();

        $error = $this->catchSchemaError(
            $validator,
            ['petType' => 'cat', 'name' => 'Fluffy', 'bark' => 'woof'],
        );

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('Additional properties are not allowed', $error->getMessage());
        self::assertStringContainsString('bark', $error->getMessage());
    }

    #[Test]
    public function di_04_dog_with_cat_only_field_meow_rejected_by_additional_properties(): void
    {
        $validator = $this->buildPetValidator();

        $error = $this->catchSchemaError(
            $validator,
            ['petType' => 'dog', 'name' => 'Rex', 'breed' => 'Lab', 'meow' => 'purr'],
        );

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('Additional properties are not allowed', $error->getMessage());
        self::assertStringContainsString('meow', $error->getMessage());
    }

    #[Test]
    public function di_04_unknown_pet_type_throws_unknown_discriminator_value(): void
    {
        $validator = $this->buildPetValidator();

        $this->expectException(UnknownDiscriminatorValueException::class);
        $this->expectExceptionMessage('Unknown discriminator value "bird"');

        $validator->validateSchema(['petType' => 'bird'], '#/components/schemas/Pet');
    }

    #[Test]
    public function di_07_response_with_cat_body_passes(): void
    {
        [$validator, $operation] = $this->buildPetValidatorWithOperation();

        $response = $this->createJsonResponse('{"petType":"cat","name":"Fluffy"}');

        $this->assertResponsePasses($validator, $response, $operation);
    }

    #[Test]
    public function di_07_response_with_dog_body_passes(): void
    {
        [$validator, $operation] = $this->buildPetValidatorWithOperation();

        $response = $this->createJsonResponse('{"petType":"dog","name":"Rex","breed":"Lab"}');

        $this->assertResponsePasses($validator, $response, $operation);
    }

    #[Test]
    public function di_07_response_with_cat_body_and_dog_field_bark_rejected(): void
    {
        [$validator, $operation] = $this->buildPetValidatorWithOperation();

        $response = $this->createJsonResponse('{"petType":"cat","name":"Fluffy","bark":"woof"}');

        $error = $this->catchResponseError($validator, $response, $operation);

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('Additional properties are not allowed', $error->getMessage());
        self::assertStringContainsString('bark', $error->getMessage());
    }

    #[Test]
    public function di_07_response_with_dog_body_and_cat_field_meow_rejected(): void
    {
        [$validator, $operation] = $this->buildPetValidatorWithOperation();

        $response = $this->createJsonResponse('{"petType":"dog","name":"Rex","breed":"Lab","meow":"purr"}');

        $error = $this->catchResponseError($validator, $response, $operation);

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('Additional properties are not allowed', $error->getMessage());
        self::assertStringContainsString('meow', $error->getMessage());
    }

    #[Test]
    public function di_07_response_with_unknown_pet_type_throws_unknown_discriminator_value(): void
    {
        [$validator, $operation] = $this->buildPetValidatorWithOperation();

        $response = $this->createJsonResponse('{"petType":"bird"}');

        $this->expectException(UnknownDiscriminatorValueException::class);
        $this->expectExceptionMessage('Unknown discriminator value "bird"');

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function ref_without_discriminator_validates_nested_required_field(): void
    {
        $validator = $this->buildRefValidator();

        $this->assertSchemaPasses(
            $validator,
            ['name' => 'John', 'address' => ['city' => 'NYC']],
            '#/components/schemas/User',
        );
    }

    #[Test]
    public function ref_without_discriminator_rejects_missing_nested_required(): void
    {
        $validator = $this->buildRefValidator();

        $error = $this->catchSchemaError(
            $validator,
            ['name' => 'John', 'address' => []],
            '#/components/schemas/User',
        );

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('city', $error->getMessage());
    }

    #[Test]
    public function ref_without_discriminator_enforces_additional_properties_on_parent(): void
    {
        $validator = $this->buildRefValidator();

        $error = $this->catchSchemaError(
            $validator,
            ['name' => 'John', 'address' => ['city' => 'NYC'], 'extra' => 'field'],
            '#/components/schemas/User',
        );

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('Additional properties are not allowed', $error->getMessage());
        self::assertStringContainsString('extra', $error->getMessage());
    }

    private function buildPetValidator(): OpenApiValidatorInterface
    {
        return $this->buildValidator(self::PET_SPEC);
    }

    private function buildRefValidator(): OpenApiValidatorInterface
    {
        return $this->buildValidator(self::REF_WITHOUT_DISCRIMINATOR_SPEC);
    }

    private function buildValidator(string $yaml): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildPetValidatorWithOperation(): array
    {
        $validator = $this->buildPetValidator();
        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/pets/123'),
        );

        return [$validator, $operation];
    }

    private function createJsonResponse(string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function assertSchemaPasses(
        OpenApiValidatorInterface $validator,
        array $data,
        string $schemaRef = '#/components/schemas/Pet',
    ): void {
        $this->assertPasses(
            fn() => $validator->validateSchema($data, $schemaRef),
            'schema validation',
        );
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function catchSchemaError(
        OpenApiValidatorInterface $validator,
        array $data,
        string $schemaRef = '#/components/schemas/Pet',
    ): ValidationException {
        return $this->catchError(
            fn() => $validator->validateSchema($data, $schemaRef),
            'schema validation',
        );
    }

    private function assertResponsePasses(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): void {
        $this->assertPasses(
            fn() => $validator->validateResponse($response, $operation),
            'response validation',
        );
    }

    private function catchResponseError(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): ValidationException {
        return $this->catchError(
            fn() => $validator->validateResponse($response, $operation),
            'response validation',
        );
    }

    private function assertPasses(callable $action, string $label): void
    {
        try {
            $action();
        } catch (ValidationException|UnknownDiscriminatorValueException $e) {
            self::fail(sprintf(
                'Expected %s to pass, but %s was thrown: %s',
                $label,
                $e::class,
                $e->getMessage(),
            ));
        }

        self::addToAssertionCount(1);
    }

    private function catchError(callable $action, string $label): ValidationException
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e;
        }

        self::fail(sprintf('Expected a %s error, but validation passed', $label));
    }
}
