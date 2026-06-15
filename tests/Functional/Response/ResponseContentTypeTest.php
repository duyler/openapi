<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function sprintf;

/**
 * RB-20: response with a Content-Type that does not match any declared
 * media type — the response body validator skips validation (fail-open,
 * documented in README), so malformed JSON bodies sneak through silently.
 * Matching negative tests confirm that validation DOES run when the
 * Content-Type matches the spec.
 *
 * RB-22: response spec declaring multiple Content-Types with different
 * schemas. Each Content-Type is validated against its own schema. Unknown
 * Content-Types silently skip validation (no UnsupportedMediaTypeException
 * on the response side, in contrast to the request side).
 */
final class ResponseContentTypeTest extends TestCase
{
    private const string JSON_RESPONSE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: JSON Response API
  version: 1.0.0
paths:
  /data:
    get:
      operationId: getData
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                required:
                  - name
                properties:
                  name:
                    type: string
YAML;

    private const string MULTIPLE_CONTENT_TYPES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multiple Content Types API
  version: 1.0.0
paths:
  /data:
    get:
      operationId: getData
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                required:
                  - jsonField
                properties:
                  jsonField:
                    type: string
            application/xml:
              schema:
                type: object
                required:
                  - xmlField
                properties:
                  xmlField:
                    type: string
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function response_with_matching_content_type_validates_body(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/json', '{"name":"John Doe"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function response_with_matching_content_type_rejects_invalid_payload(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/json', '{"unknownField":"John Doe"}');

        $caught = $this->catchValidationException($validator, $response, $operation);

        $this->assertRequiredErrorForProperty($caught, 'name');
    }

    #[Test]
    public function response_with_wrong_content_type_skips_body_validation_for_valid_json(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('text/plain', '{"name":"John Doe"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function response_with_wrong_content_type_skips_body_validation_for_invalid_json(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('text/plain', '{"name":}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function response_with_matching_content_type_rejects_malformed_json(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/json', '{"name":}');

        $this->expectException(JsonException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function response_with_unknown_vendor_content_type_skips_body_validation(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/vnd.api+json', '{"unknownField":"payload"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function response_with_matching_content_type_and_charset_suffix_validates_body(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/json; charset=utf-8', '{"name":"John Doe"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function response_with_matching_content_type_and_charset_suffix_rejects_invalid_payload(): void
    {
        [$validator, $operation] = $this->buildJsonValidatorWithOperation();

        $response = $this->createResponse('application/json; charset=utf-8', '{"unknownField":"value"}');

        $caught = $this->catchValidationException($validator, $response, $operation);

        $this->assertRequiredErrorForProperty($caught, 'name');
    }

    #[Test]
    public function multiple_content_types_response_validates_json_payload_against_json_schema(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $response = $this->createResponse('application/json', '{"jsonField":"value"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function multiple_content_types_response_rejects_payload_missing_json_required_field(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $response = $this->createResponse('application/json', '{"xmlField":"value"}');

        $caught = $this->catchValidationException($validator, $response, $operation);

        $this->assertRequiredErrorForProperty($caught, 'jsonField');
    }

    #[Test]
    public function multiple_content_types_response_validates_xml_payload_against_xml_schema(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $xmlBody = '<?xml version="1.0"?><root><xmlField>value</xmlField></root>';
        $response = $this->createResponse('application/xml', $xmlBody);

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function multiple_content_types_response_rejects_xml_payload_missing_xml_required_field(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $xmlBody = '<?xml version="1.0"?><root><jsonField>value</jsonField></root>';
        $response = $this->createResponse('application/xml', $xmlBody);

        $caught = $this->catchValidationException($validator, $response, $operation);

        $this->assertRequiredErrorForProperty($caught, 'xmlField');
    }

    #[Test]
    public function multiple_content_types_response_skips_validation_for_unknown_content_type(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $response = $this->createResponse('text/csv', 'jsonField,value');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function multiple_content_types_response_with_vendor_type_skips_validation_silently(): void
    {
        [$validator, $operation] = $this->buildMultipleContentTypesValidatorWithOperation();

        $response = $this->createResponse('application/vnd.api+json', '{"totally":"unrelated"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildJsonValidatorWithOperation(): array
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_RESPONSE_SPEC)
            ->build();

        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/data'),
        );

        return [$validator, $operation];
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildMultipleContentTypesValidatorWithOperation(): array
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPLE_CONTENT_TYPES_SPEC)
            ->build();

        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/data'),
        );

        return [$validator, $operation];
    }

    private function createResponse(string $contentType, string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->factory->createStream($body));
    }

    private function assertValidationPasses(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): void {
        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException|JsonException $exception) {
            self::fail(sprintf(
                'Expected validation to pass, but %s was thrown: %s',
                $exception::class,
                $exception->getMessage(),
            ));
        }

        self::addToAssertionCount(1);
    }

    private function catchValidationException(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): ValidationException {
        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException $exception) {
            return $exception;
        }

        self::fail('Expected ValidationException, but validation passed');
    }

    private function assertRequiredErrorForProperty(ValidationException $exception, string $property): void
    {
        $hasRequiredError = array_any(
            $exception->getErrors(),
            static fn($error): bool => $error instanceof RequiredError && $property === $error->params()['property'],
        );

        self::assertTrue(
            $hasRequiredError,
            sprintf('Expected RequiredError for property "%s" in errors list.', $property),
        );
    }
}
