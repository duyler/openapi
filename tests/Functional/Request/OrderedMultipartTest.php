<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidEncodingConfigurationException;
use Duyler\OpenApi\Validator\Exception\MissingPartHeaderError;
use Duyler\OpenApi\Validator\Exception\NotValidationError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class OrderedMultipartTest extends TestCase
{
    private const string ORDERED_UNNAMED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Ordered Unnamed Multipart
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadOrdered
      requestBody:
        required: true
        content:
          multipart/mixed:
            schema:
              type: array
              prefixItems:
              - type: object
                properties:
                  author:
                    type: string
                  created:
                    type: string
                    format: datetime
                  copyright:
                    type: string
                  license:
                    type: string
              - {}
            prefixEncoding:
            - {}
            - contentType: image/*
      responses:
        '200':
          description: Uploaded
YAML;

    private const string ORDERED_REQUIRED_HEADER_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Ordered Multipart With Required Header
  version: 1.0.0
paths:
  /page:
    post:
      operationId: uploadPage
      requestBody:
        $ref: '#/components/requestBodies/RFC2557'
components:
  headers:
    RFC2557NoContentId:
      description: Use Content-Location instead of Content-ID
      schema: false
    RFC2557ContentLocation:
      required: true
      content:
        text/plain:
          schema:
            $comment: Use a full URI (not a relative reference)
            type: string
            format: uri
  requestBodies:
    RFC2557:
      content:
        multipart/related; type=text/html:
          schema:
            prefixItems:
            - type: string
            items:
              anyOf:
              - type: string
              - $comment: To allow binary, this must always pass
          prefixEncoding:
          - contentType: text/html
            headers:
              Content-ID:
                $ref: '#/components/headers/RFC2557NoContentId'
              Content-Location:
                $ref: '#/components/headers/RFC2557ContentLocation'
          itemEncoding:
            contentType: text/css,text/javascript,image/*
            headers:
              Content-ID:
                $ref: '#/components/headers/RFC2557NoContentId'
              Content-Location:
                $ref: '#/components/headers/RFC2557ContentLocation'
YAML;

    private const string MIXED_ENCODING_MODES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Mixed Encoding Modes
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadMixed
      requestBody:
        required: true
        content:
          multipart/mixed:
            schema:
              type: array
              prefixItems:
              - type: string
            encoding:
              doc:
                contentType: application/xml
            prefixEncoding:
            - contentType: text/html
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
    public function ordered_metadata_and_image_payload_is_accepted(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: application/json',
                'content' => '{"author":"A","created":"2026-10-24T10:00:00Z","copyright":"c","license":"l"}',
            ],
            [
                'headers' => 'Content-Type: image/png',
                'content' => "PNG\x89\x50\x4E\x47",
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function string_part_on_the_object_position_is_rejected_with_type_mismatch(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: text/plain',
                'content' => 'metadata as plain text',
            ],
            [
                'headers' => 'Content-Type: image/png',
                'content' => "PNG\x89\x50",
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('object', $caught->params()['expected']);
    }

    #[Test]
    public function json_string_part_on_the_object_position_is_rejected(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: application/json',
                'content' => '"a string instead of an object"',
            ],
            [
                'headers' => 'Content-Type: image/png',
                'content' => "PNG\x89\x50",
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('object', $caught->params()['expected']);
    }

    #[Test]
    public function binary_part_on_the_first_position_is_rejected_as_out_of_order(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: image/png',
                'content' => "PNG\x89\x50\x4E",
            ],
            [
                'headers' => 'Content-Type: image/png',
                'content' => '{"author":"A"}',
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('object', $caught->params()['expected']);
    }

    #[Test]
    public function single_part_with_two_prefix_encodings_is_accepted(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: application/json',
                'content' => '{"author":"A"}',
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function image_part_without_declared_content_type_header_is_accepted_by_wildcard(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: application/json',
                'content' => '{"author":"A"}',
            ],
            [
                'headers' => 'Content-Transfer-Encoding: binary',
                'content' => "PNG\x89\x50",
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function object_part_without_content_type_header_is_decoded_as_json_by_schema_default(): void
    {
        $validator = $this->buildValidator(self::ORDERED_UNNAMED_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Disposition: form-data; name="meta"',
                'content' => '{"author":"A","created":"2026-10-24T10:00:00Z","copyright":"c","license":"l"}',
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function related_payload_with_content_location_on_every_part_is_accepted(): void
    {
        $validator = $this->buildValidator(self::ORDERED_REQUIRED_HEADER_SPEC);
        $request = $this->createMultipartRequest('related', [
            [
                'headers' => "Content-Type: text/html\r\nContent-Location: https://example.com/page",
                'content' => '<html><body>page</body></html>',
            ],
            [
                'headers' => "Content-Type: text/css\r\nContent-Location: https://example.com/page.css",
                'content' => 'body { color: black }',
            ],
            [
                'headers' => "Content-Type: image/png\r\nContent-Location: https://example.com/logo.png",
                'content' => "PNG\x89\x50",
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/page');
    }

    #[Test]
    public function part_without_required_content_location_is_rejected(): void
    {
        $validator = $this->buildValidator(self::ORDERED_REQUIRED_HEADER_SPEC);
        $request = $this->createMultipartRequest('related', [
            [
                'headers' => "Content-Type: text/html\r\nContent-Location: https://example.com/page",
                'content' => '<html><body>page</body></html>',
            ],
            [
                'headers' => 'Content-Type: text/css',
                'content' => 'body { color: black }',
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(MissingPartHeaderError::class, $caught);
        self::assertSame('Content-Location', $caught->params()['property']);
        self::assertSame(1, $caught->params()['partIndex']);
    }

    #[Test]
    public function part_carrying_content_id_is_rejected_by_the_referenced_schema(): void
    {
        $validator = $this->buildValidator(self::ORDERED_REQUIRED_HEADER_SPEC);
        $request = $this->createMultipartRequest('related', [
            [
                'headers' => "Content-Type: text/html\r\nContent-ID: <cid:page>\r\nContent-Location: https://example.com/page",
                'content' => '<html><body>page</body></html>',
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(NotValidationError::class, $caught);
        self::assertSame('not', $caught->keyword());
    }

    #[Test]
    public function mixed_encoding_modes_are_rejected_with_configuration_exception(): void
    {
        $validator = $this->buildValidator(self::MIXED_ENCODING_MODES_SPEC);
        $request = $this->createMultipartRequest('mixed', [
            [
                'headers' => 'Content-Type: text/html',
                'content' => '<html></html>',
            ],
        ]);

        $this->expectException(InvalidEncodingConfigurationException::class);

        $validator->validateRequest($request);
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function createMultipartRequest(string $subtype, array $parts): ServerRequestInterface
    {
        $boundary = "b-{$subtype}";
        $body = '';

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n"
                . $part['headers'] . "\r\n"
                . "\r\n"
                . $part['content'] . "\r\n";
        }

        $body .= "--{$boundary}--";

        return $this->factory->createServerRequest('POST', $this->pathFor($subtype))
            ->withHeader('Content-Type', "multipart/{$subtype}; boundary={$boundary}")
            ->withBody($this->factory->createStream($body));
    }

    private function pathFor(string $subtype): string
    {
        return 'related' === $subtype ? '/page' : '/upload';
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

    private function catchValidationError(
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

        self::fail('Expected AbstractValidationError, but validation passed');
    }
}
