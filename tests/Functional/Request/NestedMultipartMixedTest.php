<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MultipartNestingDepthException;
use Duyler\OpenApi\Validator\Exception\PartContentTypeMismatchError;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class NestedMultipartMixedTest extends TestCase
{
    private const string OUTER_BOUNDARY = 'outer';

    private const string INNER_BOUNDARY = 'inner';

    private const string NESTED_MIXED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Nested multipart/mixed
  version: 1.0.0
paths:
  /upload:
    post:
      operationId: uploadNested
      requestBody:
        required: true
        content:
          multipart/mixed:
            schema:
              type: array
              prefixItems:
              - type: array
              - type: array
                prefixItems:
                - type: object
                - type: string
                - {}
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

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function nested_mixed_tree_with_matching_part_types_is_accepted(): void
    {
        $validator = $this->buildValidator(self::NESTED_MIXED_SPEC);
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note><from>A</from></note>'],
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
        $validator = $this->buildValidator(self::NESTED_MIXED_SPEC);
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
    public function nesting_beyond_the_depth_limit_is_rejected_with_the_domain_exception(): void
    {
        $validator = $this->buildValidator(self::NESTED_MIXED_SPEC);
        $deeperBody = $this->body(
            'innermost',
            [
                ['headers' => 'Content-Type: text/plain', 'content' => 'deep'],
            ],
        );
        $request = $this->createNestedMixedRequest(
            innerParts: [
                [
                    'headers' => 'Content-Type: multipart/mixed; boundary=innermost',
                    'content' => $deeperBody,
                ],
                ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
                ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
            ],
        );

        try {
            $validator->validateRequest($request);
            $this->fail('Expected MultipartNestingDepthException for the third nesting level');
        } catch (MultipartNestingDepthException $exception) {
            self::assertSame('Maximum multipart nesting depth of 1 exceeded', $exception->getMessage());
        }
    }

    #[Test]
    public function nested_part_list_shorter_than_prefix_items_stays_accepted(): void
    {
        $validator = $this->buildValidator(self::NESTED_MIXED_SPEC);
        $request = $this->createNestedMixedRequest(
            innerParts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note><from>A</from></note>'],
                ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
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
        } catch (AbstractValidationError $error) {
            return $error;
        }

        self::fail('Expected AbstractValidationError, but validation passed');
    }
}
