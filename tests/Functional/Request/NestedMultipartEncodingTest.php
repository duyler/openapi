<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Test\Support\FixtureLocator;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MissingPartHeaderError;
use Duyler\OpenApi\Validator\Exception\PartContentTypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class NestedMultipartEncodingTest extends TestCase
{
    private const string OUTER_BOUNDARY = 'outer';

    private const string INNER_BOUNDARY = 'inner';

    private const string NESTED_MIXED_WITHOUT_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Nested Multipart Without Schema
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadNested
      requestBody:
        required: true
        content:
          multipart/mixed:
            prefixEncoding:
            - {}
            - contentType: multipart/mixed
              prefixEncoding:
              - contentType: application/xml
              - {}
              - contentType: image/png
      responses:
        '200':
          description: Uploaded
YAML;

    private const string NAMED_FORM_DATA_ENCODING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Named Form Data Encoding
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadForm
      requestBody:
        required: true
        content:
          multipart/form-data:
            encoding:
              doc:
                contentType: application/xml
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
    public function nested_mixed_with_matching_inner_content_types_is_accepted(): void
    {
        $validator = $this->buildValidatorFromYaml(self::NESTED_MIXED_WITHOUT_SCHEMA_SPEC);
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note/>'],
                ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
                ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function nested_part_with_wrong_content_type_is_rejected_with_encoding_error(): void
    {
        $validator = $this->buildValidatorFromFile('multipart/nested-encoding.yaml');
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/json', 'content' => '{"unexpected":true}'],
                ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
                ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
            ],
        );

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(PartContentTypeMismatchError::class, $caught);
        self::assertSame('prefixEncoding', $caught->keyword());
        self::assertSame('application/xml', $caught->params()['expected']);
        self::assertSame('application/json', $caught->params()['actual']);
    }

    #[Test]
    public function nested_part_with_missing_required_header_is_rejected(): void
    {
        $validator = $this->buildValidatorFromYaml(
            <<<'YAML'
openapi: 3.2.0
info:
  title: Nested Multipart With Required Header
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadNested
      requestBody:
        required: true
        content:
          multipart/mixed:
            prefixEncoding:
            - {}
            - contentType: multipart/mixed
              prefixEncoding:
              - contentType: application/xml
                headers:
                  X-Trace-Id:
                    required: true
                    schema:
                      type: string
      responses:
        '200':
          description: Uploaded
YAML,
        );
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note/>'],
            ],
        );

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(MissingPartHeaderError::class, $caught);
        self::assertSame('X-Trace-Id', $caught->params()['property']);
    }

    #[Test]
    public function double_nested_payload_is_passed_through_without_errors(): void
    {
        $validator = $this->buildValidatorFromYaml(self::NESTED_MIXED_WITHOUT_SCHEMA_SPEC);
        $deeperBody = $this->body(
            'deeper',
            [
                ['headers' => 'Content-Type: application/json', 'content' => '{"deep":true}'],
            ],
        );
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note/>'],
                [
                    'headers' => 'Content-Type: multipart/mixed; boundary=deeper',
                    'content' => $deeperBody,
                ],
                ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function named_form_data_part_with_matching_content_type_is_accepted(): void
    {
        $validator = $this->buildValidatorFromYaml(self::NAMED_FORM_DATA_ENCODING_SPEC);
        $request = $this->createFormDataRequest(
            parts: [
                [
                    'headers' => "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: application/xml",
                    'content' => '<note/>',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    #[Test]
    public function named_form_data_part_with_wrong_content_type_is_rejected(): void
    {
        $validator = $this->buildValidatorFromYaml(self::NAMED_FORM_DATA_ENCODING_SPEC);
        $request = $this->createFormDataRequest(
            parts: [
                [
                    'headers' => "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: text/plain",
                    'content' => 'not xml',
                ],
            ],
        );

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(PartContentTypeMismatchError::class, $caught);
        self::assertSame('encoding', $caught->keyword());
    }

    #[Test]
    public function form_data_part_without_content_type_header_stays_accepted(): void
    {
        $validator = $this->buildValidatorFromYaml(self::NAMED_FORM_DATA_ENCODING_SPEC);
        $request = $this->createFormDataRequest(
            parts: [
                [
                    'headers' => 'Content-Disposition: form-data; name="doc"',
                    'content' => '<note/>',
                ],
            ],
        );

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/upload');
    }

    /**
     * @param list<array{headers: string, content: string}> $innerParts
     */
    private function createNestedMixedRequest(array $innerParts): ServerRequestInterface
    {
        $innerBody = $this->body(self::INNER_BOUNDARY, $innerParts);

        $body = $this->body(
            self::OUTER_BOUNDARY,
            [
                ['headers' => 'Content-Type: application/json', 'content' => '[1,2]'],
                [
                    'headers' => 'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY,
                    'content' => $innerBody,
                ],
            ],
        );

        return $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', 'multipart/mixed; boundary=' . self::OUTER_BOUNDARY)
            ->withBody($this->factory->createStream($body));
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function createFormDataRequest(array $parts): ServerRequestInterface
    {
        $body = $this->body(self::OUTER_BOUNDARY, $parts);

        return $this->factory->createServerRequest('POST', '/upload')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . self::OUTER_BOUNDARY)
            ->withBody($this->factory->createStream($body));
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function body(string $boundary, array $parts): string
    {
        $body = '';

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n"
                . $part['headers'] . "\r\n"
                . "\r\n"
                . $part['content'] . "\r\n";
        }

        return $body . "--{$boundary}--";
    }

    private function buildValidatorFromYaml(string $spec): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();
    }

    private function buildValidatorFromFile(string $relativePath): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile(FixtureLocator::path($relativePath))
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
