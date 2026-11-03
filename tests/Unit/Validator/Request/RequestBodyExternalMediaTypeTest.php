<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

use function base64_encode;

#[CoversClass(RequestBodyValidatorWithContext::class)]
final class RequestBodyExternalMediaTypeTest extends TestCase
{
    private RequestBodyValidatorWithContext $validator;

    private OpenApiDocument $document;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
        );

        $bodyParser = new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(new QueryParser()),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        );

        $this->validator = new RequestBodyValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $pool,
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create(), contentAssertionsEnabled: true),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: $bodyParser,
                contentAssertionsEnabled: true,
            ),
        );
    }

    #[Test]
    public function root_content_media_type_check_is_skipped_when_content_map_key_defines_media_type(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'string', contentMediaType: 'application/xml'),
                ),
            ]),
        );

        $succeeded = false;

        try {
            $this->validator->validate('"not xml"', 'application/json', $requestBody);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected root content media type check to be skipped, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function nested_content_media_type_is_still_asserted_under_external_media_type(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'embedded' => new Schema(type: 'string', contentMediaType: 'application/xml'),
                        ],
                    ),
                ),
            ]),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('{"embedded":"not xml"}', 'application/json', $requestBody);
    }

    #[Test]
    public function root_content_schema_still_applies_when_content_map_key_defines_media_type(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'string',
                        contentMediaType: 'application/xml',
                        contentSchema: new Schema(type: 'object'),
                    ),
                ),
            ]),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('"not xml"', 'application/json', $requestBody);
    }

    #[Test]
    public function root_content_encoding_still_applies_when_content_map_key_defines_media_type(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'string',
                        contentMediaType: 'application/xml',
                        contentEncoding: 'base64',
                    ),
                ),
            ]),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate('"!!not-base64!!"', 'application/json', $requestBody);
    }

    #[Test]
    public function root_decoded_content_matches_content_schema_when_content_map_key_defines_media_type(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'string',
                        contentMediaType: 'application/xml',
                        contentEncoding: 'base64',
                        contentSchema: new Schema(type: 'string', minLength: 3),
                    ),
                ),
            ]),
        );

        $succeeded = false;

        try {
            $this->validator->validate(
                sprintf('"%s"', base64_encode('plain text')),
                'application/json',
                $requestBody,
            );
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected decoded content to satisfy content schema, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }
}
