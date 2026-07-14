<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Response\ResponseBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Override;
use RuntimeException;

use function sprintf;

/** @internal */
final class ResponseBodyValidatorWithContextTest extends TestCase
{
    private ResponseBodyValidatorWithContext $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
        );
        $negotiator = new ContentTypeNegotiator();
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser(new QueryParser());
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();
        $typeCoercer = new ResponseTypeCoercer();
        $bodyParser = new BodyParser($jsonParser, $formParser, $multipartParser, $textParser, $xmlParser);

        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            bodyParser: $bodyParser,
        );

        $this->validator = new ResponseBodyValidatorWithContext(
            document: $document,
            dependencies: $dependencies,
        );
    }

    #[Test]
    public function validate_json_response(): void
    {
        $body = '{"id":123,"name":"John"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                        'name' => new Schema(type: 'string'),
                    ],
                    required: ['id', 'name'],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_validation_when_content_is_null(): void
    {
        $body = '{"id":123}';
        $contentType = 'application/json';

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, null);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_validation_when_media_type_not_found(): void
    {
        $body = '{"id":123}';
        $contentType = 'application/xml';
        $content = new Content([
            'application/json' => new MediaType(),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_jsonl_streaming_response(): void
    {
        $body = '{"id":1}' . "\n" . '{"id":2}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                    required: ['id'],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_ndjson_streaming_response(): void
    {
        $body = '{"count":1}' . "\n" . '{"count":2}';
        $contentType = 'application/x-ndjson';
        $content = new Content([
            'application/x-ndjson' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'count' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_json_seq_streaming_response(): void
    {
        $body = "\x1E" . '{"id":"1"}' . "\x1E" . '{"id":"2"}';
        $contentType = 'application/json-seq';
        $content = new Content([
            'application/json-seq' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'string'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_streaming_uses_schema_when_item_schema_not_defined(): void
    {
        $body = '{"fallback":true}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'fallback' => new Schema(type: 'boolean'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_streaming_validation_when_no_schema(): void
    {
        $body = '{"data":"value"}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_with_invalid_json_line(): void
    {
        $body = '{"id":1}' . "\n" . 'invalid json';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_invalid_item_throws_error(): void
    {
        $body = '{"id":"not_an_integer"}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                    required: ['id'],
                ),
            ),
        ]);

        $caught = null;

        try {
            $this->validator->validate($body, $contentType, $content);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
    }

    #[Test]
    public function streaming_with_empty_body(): void
    {
        $body = '';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'object'),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_unknown_content_type(): void
    {
        $body = '{"data":"value"}';
        $contentType = 'application/unknown-stream';
        $content = new Content([
            'application/unknown-stream' => new MediaType(
                itemSchema: new Schema(type: 'object'),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_with_coercion_enabled(): void
    {
        $pool = new ValidatorPool();
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
        );
        $negotiator = new ContentTypeNegotiator();
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser(new QueryParser());
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();
        $typeCoercer = new ResponseTypeCoercer();
        $bodyParser = new BodyParser($jsonParser, $formParser, $multipartParser, $textParser, $xmlParser);

        $validator = new ResponseBodyValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $pool,
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: $bodyParser,
            ),
            configuration: new ValidatorConfiguration(coercion: true),
        );

        $body = '{"id":"123"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_with_item_encoding(): void
    {
        $body = '{"id":1}' . "\n" . '{"id":2}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
                itemEncoding: new Encoding(
                    contentType: 'application/jsonl',
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_json_seq_with_record_separator(): void
    {
        $body = "\x1E" . '{"record":"first"}' . "\n\x1E" . '{"record":"second"}';
        $contentType = 'application/json-seq';
        $content = new Content([
            'application/json-seq' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'record' => new Schema(type: 'string'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_json_seq_invalid_json(): void
    {
        $body = "\x1E" . 'invalid json';
        $contentType = 'application/json-seq';
        $content = new Content([
            'application/json-seq' => new MediaType(
                itemSchema: new Schema(type: 'object'),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function streaming_with_null_item_in_list(): void
    {
        $body = '{"id":1}' . "\n" . 'null' . "\n" . '{"id":2}';
        $contentType = 'application/jsonl';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_sse_streaming_response(): void
    {
        $body = "event: message\n" . "data: {\"text\":\"hello\"}\n\n";
        $contentType = 'text/event-stream';
        $content = new Content([
            'text/event-stream' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'event' => new Schema(type: 'string'),
                        'data' => new Schema(type: 'object'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_sse_with_multiple_events(): void
    {
        $body = "event: message\ndata: {\"count\":1}\n\nevent: update\ndata: {\"count\":2}\n\n";
        $contentType = 'text/event-stream';
        $content = new Content([
            'text/event-stream' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'event' => new Schema(type: 'string'),
                        'data' => new Schema(
                            type: 'object',
                            properties: [
                                'count' => new Schema(type: 'integer'),
                            ],
                        ),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_sse_with_id_and_comments(): void
    {
        $body = ": this is a comment\nid: 123\nevent: message\ndata: {\"text\":\"test\"}\n\n";
        $contentType = 'text/event-stream';
        $content = new Content([
            'text/event-stream' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'event' => new Schema(type: 'string'),
                        'data' => new Schema(type: 'object'),
                        'id' => new Schema(type: 'string'),
                    ],
                ),
            ),
        ]);

        $succeeded = false;
        try {
            $this->validator->validate($body, $contentType, $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
