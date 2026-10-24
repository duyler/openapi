<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Event\UnsupportedResponseMediaTypeWarningEvent;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Test\Support\RecordingEventDispatcher;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Response\ResponseBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function sprintf;

#[CoversClass(ResponseBodyValidatorWithContext::class)]
final class ResponseBodyExternalMediaTypeTest extends TestCase
{
    private ResponseBodyValidatorWithContext $validator;

    private SchemaValidatorDependencies $dependencies;

    private OpenApiDocument $document;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
        );
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser(new QueryParser());
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();
        $bodyParser = new BodyParser($jsonParser, $formParser, $multipartParser, $textParser, $xmlParser);

        $this->dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create(), contentAssertionsEnabled: true),
            formatRegistry: BuiltinFormats::create(),
            bodyParser: $bodyParser,
            contentAssertionsEnabled: true,
        );

        $this->validator = new ResponseBodyValidatorWithContext(
            document: $this->document,
            dependencies: $this->dependencies,
        );
    }

    #[Test]
    public function root_content_media_type_check_is_skipped_when_content_map_key_defines_media_type(): void
    {
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(type: 'string', contentMediaType: 'application/xml'),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validate('"not xml"', 'application/json', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function nested_content_media_type_is_still_asserted_under_external_media_type(): void
    {
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'payload' => new Schema(type: 'string', contentMediaType: 'application/xml'),
                    ],
                ),
            ),
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate('{"payload":"not xml"}', 'application/json', $content);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_streaming_body_items(): void
    {
        $body = '"not xml"' . "\n" . '"still not xml"';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validate($body, 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function nested_item_content_media_type_is_still_asserted_for_streaming_body_items(): void
    {
        $body = '{"embedded":"not xml"}';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'embedded' => new Schema(type: 'string', contentMediaType: 'application/xml'),
                    ],
                ),
            ),
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($body, 'application/jsonl', $content);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_streaming_body_items_with_item_encoding(): void
    {
        $body = '"not xml"' . "\n" . '"still not xml"';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
                itemEncoding: new Encoding(contentType: 'application/jsonl'),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validate($body, 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_stream_interface_items(): void
    {
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validateStream($this->createStream('"not xml"' . "\n" . '"still not xml"'), 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function nested_item_content_media_type_is_still_asserted_for_stream_interface_items(): void
    {
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(
                    type: 'object',
                    properties: [
                        'embedded' => new Schema(type: 'string', contentMediaType: 'application/xml'),
                    ],
                ),
            ),
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validateStream($this->createStream('{"embedded":"not xml"}'), 'application/jsonl', $content);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_stream_interface_items_with_item_encoding(): void
    {
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
                itemEncoding: new Encoding(contentType: 'application/jsonl'),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validateStream($this->createStream('"not xml"' . "\n" . '"still not xml"'), 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_stream_interface_items_with_content_type_list(): void
    {
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
                itemEncoding: new Encoding(contentType: ['application/jsonl', 'image/*']),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validateStream($this->createStream('"not xml"' . "\n" . '"still not xml"'), 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function first_element_of_item_encoding_content_type_list_selects_the_stream_item_parser(): void
    {
        $content = new Content([
            'text/plain' => new MediaType(
                itemSchema: new Schema(type: 'object'),
                itemEncoding: new Encoding(contentType: ['application/jsonl', 'image/*']),
            ),
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validateStream($this->createStream('"scalar"' . "\n"), 'text/plain', $content);
    }

    #[Test]
    public function item_root_content_media_type_check_is_skipped_for_streaming_body_with_content_type_list(): void
    {
        $body = '"not xml"' . "\n" . '"still not xml"';
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'string', contentMediaType: 'application/xml'),
                itemEncoding: new Encoding(contentType: ['application/jsonl', 'image/*']),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validate($body, 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected item root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function first_element_of_item_encoding_content_type_list_selects_the_streaming_body_parser(): void
    {
        $body = '"scalar"' . "\n";
        $content = new Content([
            'application/jsonl' => new MediaType(
                itemSchema: new Schema(type: 'object'),
                itemEncoding: new Encoding(contentType: ['image/*', 'application/jsonl']),
            ),
        ]);

        $succeeded = false;

        try {
            $this->validator->validate($body, 'application/jsonl', $content);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected the first list element to select the item parser, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function content_media_type_check_applies_without_external_media_type(): void
    {
        $schemaValidator = $this->dependencies->rootSchemaValidator(
            $this->document,
            new ValidatorConfiguration(),
        );

        $this->expectException(ValidationException::class);

        $schemaValidator->validate('not xml', new Schema(type: 'string', contentMediaType: 'application/xml'), ValidatorMode::Response);
    }

    #[Test]
    public function skip_stream_interface_validation_when_content_is_null(): void
    {
        $this->validator->validateStream($this->createStream('"x"'), 'application/jsonl', null);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function skip_stream_interface_validation_when_media_type_not_found(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $content = new Content([
            'application/json' => new MediaType(),
        ]);
        $validator = new ResponseBodyValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: new ValidatorPool(),
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: $this->dependencies->bodyParser,
                eventDispatcher: $dispatcher,
            ),
            configuration: new ValidatorConfiguration(lenientResponses: true),
        );

        $validator->validateStream($this->createStream('"x"'), 'application/jsonl', $content);

        self::assertCount(1, $dispatcher->events);
        $warning = $dispatcher->events[0];
        self::assertInstanceOf(UnsupportedResponseMediaTypeWarningEvent::class, $warning);
        self::assertSame('application/jsonl', $warning->mediaType);
    }

    #[Test]
    public function skip_stream_interface_validation_when_no_schema(): void
    {
        $content = new Content([
            'application/jsonl' => new MediaType(),
        ]);

        $this->validator->validateStream($this->createStream('"x"'), 'application/jsonl', $content);

        $this->addToAssertionCount(1);
    }

    private function createStream(string $body): StreamInterface
    {
        return new Psr17Factory()->createStream($body);
    }
}
