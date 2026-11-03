<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidContentRangeException;
use Duyler\OpenApi\Validator\Exception\MissingPartHeaderError;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class StreamingMultipartTest extends TestCase
{
    private const string BYTE_RANGES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Byte Ranges
  version: 1.0.0
paths:
  /video:
    post:
      operationId: uploadRanges
      requestBody:
        required: true
        content:
          multipart/byteranges:
            itemSchema:
              $comment: A single range of bytes from a video
            itemEncoding:
              contentType: video/mp4
              headers:
                Content-Range:
                  required: true
                  content:
                    text/plain:
                      schema:
                        type: string
      responses:
        '200':
          description: Uploaded
YAML;

    private const string BYTE_RANGES_PATTERN_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Byte Ranges With Pattern
  version: 1.0.0
paths:
  /video:
    post:
      operationId: uploadRanges
      requestBody:
        required: true
        content:
          multipart/byteranges:
            itemSchema:
              $comment: A single range of bytes from a video
            itemEncoding:
              contentType: video/mp4
              headers:
                Content-Range:
                  required: true
                  content:
                    text/plain:
                      schema:
                        type: string
                        pattern: '^bytes [0-9]+-[0-9]+/[0-9]+$'
      responses:
        '200':
          description: Uploaded
YAML;

    private const string STREAMING_IMAGES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Multipart
  version: 1.0.0
paths:
  /images:
    post:
      operationId: uploadImages
      requestBody:
        required: true
        content:
          multipart/mixed:
            itemSchema:
              $comment: A single data image from the device
            itemEncoding:
              contentType: image/jpg
      responses:
        '200':
          description: Uploaded
YAML;

    private const string STREAMING_EXAMPLE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Multipart Example
  version: 1.0.0
paths:
  /images:
    post:
      operationId: uploadImages
      requestBody:
        required: true
        content:
          multipart/mixed:
            itemSchema:
              type: string
              example: expected-image
            itemEncoding:
              contentType: image/jpg
      responses:
        '200':
          description: Uploaded
YAML;

    private const string STREAMING_ITEM_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Multipart Item Schema
  version: 1.0.0
paths:
  /items:
    post:
      operationId: uploadItems
      requestBody:
        required: true
        content:
          multipart/mixed:
            schema:
              type: array
              items:
                type: string
            itemSchema:
              type: integer
            itemEncoding:
              contentType: text/plain
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
    public function sized_content_range_part_is_accepted(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => "Content-Type: video/mp4\r\nContent-Range: bytes 0-100/1000",
                'content' => str_repeat('v', 101),
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/video');
    }

    #[Test]
    public function unsized_content_range_part_is_accepted(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => "Content-Type: video/mp4\r\nContent-Range: bytes */1000",
                'content' => str_repeat('v', 64),
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/video');
    }

    #[Test]
    public function missing_required_content_range_is_rejected(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => 'Content-Type: video/mp4',
                'content' => str_repeat('v', 64),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(MissingPartHeaderError::class, $caught);
        self::assertSame('Content-Range', $caught->params()['property']);
    }

    #[Test]
    public function inverted_content_range_is_rejected(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => "Content-Type: video/mp4\r\nContent-Range: bytes 200-100/1000",
                'content' => str_repeat('v', 64),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(InvalidContentRangeException::class, $caught);
        self::assertSame(200, $caught->params()['first']);
        self::assertSame(100, $caught->params()['last']);
    }

    #[Test]
    public function syntactically_broken_content_range_is_rejected(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => "Content-Type: video/mp4\r\nContent-Range: bytes x-y/z",
                'content' => str_repeat('v', 64),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertInstanceOf(InvalidContentRangeException::class, $caught);
        self::assertSame('format', $caught->keyword());
    }

    #[Test]
    public function unanchored_content_range_forms_are_rejected(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_SPEC);

        foreach (['bytes 0-100', '*/1000', 'bytes 0-100/1000junk'] as $headerValue) {
            $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
                [
                    'headers' => "Content-Type: video/mp4\r\nContent-Range: {$headerValue}",
                    'content' => str_repeat('v', 64),
                ],
            ]);

            $caught = $this->catchValidationError($validator, $request);

            self::assertInstanceOf(
                InvalidContentRangeException::class,
                $caught,
                "Expected the malformed range [{$headerValue}] to be rejected",
            );
        }
    }

    #[Test]
    public function content_based_header_schema_rejects_out_of_pattern_range(): void
    {
        $validator = $this->buildValidator(self::BYTE_RANGES_PATTERN_SPEC);
        $request = $this->createMultipartRequest('/video', 'multipart/byteranges', [
            [
                'headers' => "Content-Type: video/mp4\r\nContent-Range: bytes 0-100/*",
                'content' => str_repeat('v', 101),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertSame('pattern', $caught->keyword());
    }

    #[Test]
    public function image_sequence_is_processed_incrementally(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->createMultipartRequest('/images', 'multipart/mixed', [
            [
                'headers' => 'Content-Type: image/jpg',
                'content' => "\xFF\xD8\xFF" . str_repeat('j', 128),
            ],
            [
                'headers' => '',
                'content' => "\xFF\xD8\xFF" . str_repeat('k', 128),
            ],
            [
                'headers' => 'Content-Type: image/jpg',
                'content' => "\xFF\xD8\xFF" . str_repeat('l', 128),
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/images');
    }

    #[Test]
    public function image_part_with_unexpected_content_type_is_rejected(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->createMultipartRequest('/images', 'multipart/mixed', [
            [
                'headers' => 'Content-Type: video/mp4',
                'content' => str_repeat('v', 64),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertSame('itemEncoding', $caught->keyword());
    }

    #[Test]
    public function empty_stream_against_required_body_is_rejected(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->factory->createServerRequest('POST', '/images')
            ->withHeader('Content-Type', 'multipart/mixed; boundary=mixed')
            ->withBody($this->factory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function binary_content_with_a_fake_close_marker_validates_both_parts(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->createMultipartRequest('/images', 'multipart/mixed', [
            [
                'headers' => 'Content-Type: image/jpg',
                'content' => "\xFF\xD8\xFF" . "fake\r\n--stream--JUNK\r\nevil-tail",
            ],
            [
                'headers' => 'Content-Type: image/jpg',
                'content' => "\xFF\xD8\xFF" . str_repeat('k', 16),
            ],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/images');
    }

    #[Test]
    public function part_hidden_behind_a_fake_close_marker_is_still_validated(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->createMultipartRequest('/images', 'multipart/mixed', [
            [
                'headers' => 'Content-Type: image/jpg',
                'content' => "\xFF\xD8\xFF" . "fake\r\n--stream--JUNK\r\nevil-tail",
            ],
            [
                'headers' => 'Content-Type: video/mp4',
                'content' => str_repeat('v', 16),
            ],
        ]);

        $caught = $this->catchValidationError($validator, $request);

        self::assertSame('itemEncoding', $caught->keyword());
    }

    #[Test]
    public function item_schema_governs_part_coercion_over_the_body_items_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_ITEM_SCHEMA_SPEC)
            ->enableCoercion()
            ->build();

        $operation = $validator->validateRequest(
            $this->createMultipartRequest('/items', 'multipart/mixed', [
                [
                    'headers' => 'Content-Type: text/plain',
                    'content' => '7',
                ],
            ]),
        );

        $this->assertOperationMatches($operation, 'POST', '/items');

        $caught = $this->catchValidationError(
            $validator,
            $this->createMultipartRequest('/items', 'multipart/mixed', [
                [
                    'headers' => 'Content-Type: text/plain',
                    'content' => 'x',
                ],
            ]),
        );

        self::assertSame('type', $caught->keyword());
    }

    #[Test]
    public function part_example_mismatch_dispatches_a_warning_per_part(): void
    {
        $warnings = [];

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_EXAMPLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createMultipartRequest('/images', 'multipart/mixed', [
            ['headers' => 'Content-Type: image/jpg', 'content' => 'unexpected-image'],
            ['headers' => 'Content-Type: image/jpg', 'content' => 'expected-image'],
        ]);

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/images');

        self::assertCount(1, $warnings);
        self::assertSame(
            'Data does not match any of the declared examples (data: "unexpected-image")',
            $warnings[0]->message,
        );
    }

    #[Test]
    public function whitespace_only_streaming_body_with_a_required_body_is_not_rejected(): void
    {
        $validator = $this->buildValidator(self::STREAMING_IMAGES_SPEC);
        $request = $this->factory->createServerRequest('POST', '/images')
            ->withHeader('Content-Type', 'multipart/mixed; boundary=mixed')
            ->withBody($this->factory->createStream("   \r\n"));

        $operation = $validator->validateRequest($request);

        $this->assertOperationMatches($operation, 'POST', '/images');
    }

    private function buildValidator(string $spec): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function createMultipartRequest(
        string $path,
        string $mediaType,
        array $parts,
    ): ServerRequestInterface {
        $boundary = 'stream';
        $body = '';

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n"
                . $part['headers'] . "\r\n"
                . "\r\n"
                . $part['content'] . "\r\n";
        }

        $body .= "--{$boundary}--";

        return $this->factory->createServerRequest('POST', $path)
            ->withHeader('Content-Type', "{$mediaType}; boundary={$boundary}")
            ->withBody($this->factory->createStream($body));
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
