<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * RB-12: multipart/form-data file upload — characterization tests for the
 * multipart body parser behaviour. The parser returns a list of parts
 * (each with "headers" and "content" keys) which is why a spec schema
 * must use type: array to accept multipart bodies, while type: object
 * is always rejected with a TypeMismatchError.
 */
final class MultipartUploadTest extends TestCase
{
    private const string MULTIPART_ARRAY_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Array Upload API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: array
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_MIN_ITEMS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Min Items API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: array
              minItems: 3
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_MAX_ITEMS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Max Items API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: array
              maxItems: 1
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_OBJECT_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Object Schema API
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required:
                - file
              properties:
                file:
                  type: string
                  format: binary
      responses:
        '200':
          description: Uploaded
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function multipart_with_single_file_upload_passes_validation(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_ARRAY_SCHEMA_SPEC);
        $request = $this->createMultipartRequest(
            '----WebKitFormBoundary',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="test.txt"',
                    'content' => 'file content here',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_with_multiple_file_uploads_passes_validation(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_ARRAY_SCHEMA_SPEC);
        $request = $this->createMultipartRequest(
            '----WebKitFormBoundary',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file1"; filename="a.txt"',
                    'content' => 'content a',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="file2"; filename="b.txt"',
                    'content' => 'content b',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="metadata"',
                    'content' => '{"size":2}',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_with_field_and_file_passes_validation(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_ARRAY_SCHEMA_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="title"',
                    'content' => 'Annual Report',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="report.pdf"',
                    'content' => 'binary blob',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_below_min_items_rejected_with_min_items_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MIN_ITEMS_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="single.txt"',
                    'content' => 'only one part',
                ],
            ],
        );

        $caught = $this->catchSchemaError($validator, $request);

        self::assertInstanceOf(MinItemsError::class, $caught);
        self::assertSame('minItems', $caught->keyword());
    }

    #[Test]
    public function multipart_meeting_min_items_passes_validation(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MIN_ITEMS_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                ['headers' => 'Content-Disposition: form-data; name="a"', 'content' => '1'],
                ['headers' => 'Content-Disposition: form-data; name="b"', 'content' => '2'],
                ['headers' => 'Content-Disposition: form-data; name="c"', 'content' => '3'],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_exceeding_max_items_rejected_with_max_items_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MAX_ITEMS_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                ['headers' => 'Content-Disposition: form-data; name="a"', 'content' => '1'],
                ['headers' => 'Content-Disposition: form-data; name="b"', 'content' => '2'],
            ],
        );

        $caught = $this->catchSchemaError($validator, $request);

        self::assertInstanceOf(MaxItemsError::class, $caught);
        self::assertSame('maxItems', $caught->keyword());
    }

    #[Test]
    public function multipart_with_binary_format_against_object_schema_rejected_with_required_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_OBJECT_SCHEMA_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="data.bin"',
                    'content' => 'binary data',
                ],
            ],
        );

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ValidationException $exception) {
            $caught = $exception;
        }

        self::assertNotNull(
            $caught,
            'Multipart body with binary format must not populate the "file" property of an object schema.',
        );

        $hasRequiredError = array_any(
            $caught->getErrors(),
            static fn($error): bool => $error instanceof RequiredError && 'file' === $error->params()['property'],
        );

        self::assertTrue(
            $hasRequiredError,
            'Expected RequiredError for missing "file" property: multipart parser does not extract form field names into object schema properties.',
        );
    }

    #[Test]
    public function multipart_without_boundary_returns_empty_part_list_passes_array_schema(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_ARRAY_SCHEMA_SPEC);

        $bodyWithoutBoundary = "Content-Disposition: form-data; name=\"field\"\r\n\r\nvalue";
        $request = $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->withBody($this->factory->createStream($bodyWithoutBoundary));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_without_boundary_against_min_items_rejected_with_min_items_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MIN_ITEMS_SPEC);

        $bodyWithoutBoundary = "Content-Disposition: form-data; name=\"field\"\r\n\r\nvalue";
        $request = $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->withBody($this->factory->createStream($bodyWithoutBoundary));

        $caught = $this->catchSchemaError($validator, $request);

        self::assertInstanceOf(MinItemsError::class, $caught);
        self::assertSame('minItems', $caught->keyword());
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function createMultipartRequest(string $boundary, array $parts): ServerRequestInterface
    {
        $body = "boundary={$boundary}\r\n\r\n";

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n"
                . $part['headers'] . "\r\n"
                . "\r\n"
                . $part['content'] . "\r\n";
        }

        $body .= "--{$boundary}--";

        return $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->factory->createStream($body));
    }

    private function buildValidator(string $spec): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();
    }

    private function assertOperationMatches(Operation $operation, string $method, string $path): void
    {
        self::assertSame($method, $operation->method);
        self::assertSame($path, $operation->path);
    }

    private function catchSchemaError(
        OpenApiValidatorInterface $validator,
        ServerRequestInterface $request,
    ): AbstractValidationError {
        try {
            $validator->validateRequest($request);
        } catch (ValidationException $exception) {
            foreach ($exception->getErrors() as $error) {
                if ($error instanceof AbstractValidationError) {
                    return $error;
                }
            }
        } catch (AbstractValidationError $error) {
            return $error;
        }

        self::fail('Expected AbstractValidationError-derived schema error, but validation passed');
    }
}
