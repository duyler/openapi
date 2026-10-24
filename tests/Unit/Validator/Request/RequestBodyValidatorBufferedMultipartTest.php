<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Test\Support\StreamStubHelper;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/** @internal */
final class RequestBodyValidatorBufferedMultipartTest extends TestCase
{
    use StreamStubHelper;

    private RequestBodyValidatorWithContext $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();

        $this->validator = new RequestBodyValidatorWithContext(
            document: new OpenApiDocument(
                openapi: '3.2.0',
                info: new InfoObject(title: 'Buffered Multipart', version: '1.0.0'),
            ),
            dependencies: new SchemaValidatorDependencies(
                pool: $pool,
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: new BodyParser(
                    jsonParser: new JsonBodyParser(),
                    formParser: new FormBodyParser(new QueryParser()),
                    multipartParser: new MultipartBodyParser(),
                    textParser: new TextBodyParser(),
                    xmlParser: new XmlBodyParser(),
                ),
            ),
        );
    }

    #[Test]
    public function buffered_ordered_multipart_validates_the_positional_instance(): void
    {
        $mediaType = new MediaType(
            schema: new Schema(
                type: 'array',
                prefixItems: [new Schema(type: 'string'), new Schema(type: 'string')],
            ),
            prefixEncoding: [new Encoding(contentType: 'text/plain'), new Encoding()],
        );

        $this->validator->validate(
            $this->orderedBody(),
            'multipart/mixed; boundary=b',
            new RequestBody(required: true, content: new Content(['multipart/mixed' => $mediaType])),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function buffered_ordered_multipart_rejects_a_type_mismatch(): void
    {
        $mediaType = new MediaType(
            schema: new Schema(
                type: 'array',
                prefixItems: [new Schema(type: 'object'), new Schema(type: 'string')],
            ),
            prefixEncoding: [new Encoding(contentType: 'text/plain'), new Encoding()],
        );

        try {
            $this->validator->validate(
                $this->orderedBody(),
                'multipart/mixed; boundary=b',
                new RequestBody(required: true, content: new Content(['multipart/mixed' => $mediaType])),
            );

            $this->fail('Expected the string part on the object position to be rejected');
        } catch (ValidationException $exception) {
            self::assertSame('type', $exception->getErrors()[0]->keyword());
        }
    }

    #[Test]
    public function multipart_without_encodings_keeps_the_raw_body_for_non_form_data(): void
    {
        $this->validator->validate(
            "--b\r\n\r\nraw content\r\n--b--",
            'multipart/mixed; boundary=b',
            new RequestBody(
                required: true,
                content: new Content(['multipart/mixed' => new MediaType()]),
            ),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function multipart_with_an_encoding_map_keeps_the_raw_body_for_non_form_data(): void
    {
        $mediaType = new MediaType(
            schema: new Schema(type: 'string'),
            encoding: ['doc' => new Encoding(contentType: 'application/xml')],
        );

        $this->validator->validate(
            "--b\r\nContent-Disposition: form-data; name=\"doc\"\r\n\r\n<doc/>\r\n--b--",
            'multipart/mixed; boundary=b',
            new RequestBody(required: true, content: new Content(['multipart/mixed' => $mediaType])),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function streaming_entry_ignores_a_missing_request_body(): void
    {
        $this->validator->validateStream(
            $this->createStub(StreamInterface::class),
            'multipart/mixed; boundary=b',
            null,
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function streaming_entry_requires_a_body_when_content_is_absent(): void
    {
        $this->expectException(MissingRequestBodyException::class);

        $this->validator->validateStream(
            $this->createStub(StreamInterface::class),
            'multipart/mixed; boundary=b',
            new RequestBody(required: true),
        );
    }

    #[Test]
    public function streaming_entry_allows_an_optional_body_without_content(): void
    {
        $this->validator->validateStream(
            $this->createStub(StreamInterface::class),
            'multipart/mixed; boundary=b',
            new RequestBody(required: false),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function streaming_entry_rejects_an_unmatched_media_type(): void
    {
        $stream = $this->createStub(StreamInterface::class);

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->validator->validateStream(
            $stream,
            'application/json',
            new RequestBody(
                required: true,
                content: new Content(['multipart/mixed' => new MediaType()]),
            ),
        );
    }

    private function orderedBody(): string
    {
        return "--b\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . 'first' . "\r\n"
            . "--b\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . 'second' . "\r\n"
            . '--b--';
    }
}
