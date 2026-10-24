<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

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

    private const string MULTIPART_MULTIPLE_FILES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Multiple Files API
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
              properties:
                file:
                  type: array
                  items: {}
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_MULTIPLE_FILES_MAX_ITEMS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Multiple Files Max Items API
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
              properties:
                file:
                  type: array
                  maxItems: 1
                  items: {}
      responses:
        '200':
          description: Uploaded
YAML;

    private const string MULTIPART_SINGLE_FILE_MIN_ITEMS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Multipart Single File Min Items API
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
              properties:
                file:
                  type: array
                  minItems: 2
                  items: {}
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
    public function multipart_part_named_file_populates_object_property_and_satisfies_required(): void
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

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_with_multiple_files_of_one_field_collects_into_array_property(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MULTIPLE_FILES_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="a.txt"',
                    'content' => 'content a',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="b.txt"',
                    'content' => 'content b',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_with_single_file_of_array_field_wraps_into_single_item_array(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MULTIPLE_FILES_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="a.txt"',
                    'content' => 'content a',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function multipart_with_duplicate_file_names_against_scalar_property_is_rejected(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_OBJECT_SCHEMA_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="a.bin"',
                    'content' => 'content a',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="b.bin"',
                    'content' => 'content b',
                ],
            ],
        );

        $caught = $this->catchSchemaError($validator, $request);

        self::assertNotNull($caught);
        self::assertSame('type', $caught->keyword());
    }

    #[Test]
    public function multipart_with_too_many_files_of_one_field_is_rejected_with_max_items_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_MULTIPLE_FILES_MAX_ITEMS_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="a.txt"',
                    'content' => 'content a',
                ],
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="b.txt"',
                    'content' => 'content b',
                ],
            ],
        );

        $caught = $this->catchSchemaError($validator, $request);

        self::assertInstanceOf(MaxItemsError::class, $caught);
        self::assertSame('maxItems', $caught->keyword());
    }

    #[Test]
    public function multipart_with_a_single_file_below_min_items_is_rejected_with_min_items_error(): void
    {
        $validator = $this->buildValidator(self::MULTIPART_SINGLE_FILE_MIN_ITEMS_SPEC);
        $request = $this->createMultipartRequest(
            'boundaryXYZ',
            [
                [
                    'headers' => 'Content-Disposition: form-data; name="file"; filename="a.txt"',
                    'content' => 'content a',
                ],
            ],
        );

        $caught = $this->catchSchemaError($validator, $request);

        self::assertInstanceOf(MinItemsError::class, $caught);
        self::assertSame('minItems', $caught->keyword());
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
