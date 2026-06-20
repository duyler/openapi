<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Model\InfoObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RequestBodyRequiredTest extends TestCase
{
    private RequestBodyValidatorWithContext $validator;
    private OpenApiDocument $document;

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
                statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
                formatRegistry: BuiltinFormats::create(),
                bodyParser: $bodyParser,
            ),
        );
    }

    #[Test]
    public function throw_exception_when_required_and_empty_body(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->expectException(MissingRequestBodyException::class);
        $this->expectExceptionMessage('Request body is required but missing or empty');

        $this->validator->validate('', 'application/json', $requestBody);
    }

    #[Test]
    public function throw_exception_when_required_and_whitespace_only_body(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->expectException(MissingRequestBodyException::class);

        $this->validator->validate('   ', 'application/json', $requestBody);
    }

    #[Test]
    public function throw_exception_when_required_and_null_content(): void
    {
        $requestBody = new RequestBody(required: true);

        $this->expectException(MissingRequestBodyException::class);

        $this->validator->validate('', 'application/json', $requestBody);
    }

    #[Test]
    public function pass_when_not_required_and_empty_body(): void
    {
        $requestBody = new RequestBody(
            required: false,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->validator->validate('', 'application/json', $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_when_not_required_and_null_content(): void
    {
        $requestBody = new RequestBody(required: false);

        $this->validator->validate('', 'application/json', $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_when_required_and_valid_body(): void
    {
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ),
            ]),
        );

        $this->validator->validate('{"name":"John"}', 'application/json', $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_when_required_body_is_null(): void
    {
        $this->validator->validate('', 'application/json', null);

        $this->expectNotToPerformAssertions();
    }
}
