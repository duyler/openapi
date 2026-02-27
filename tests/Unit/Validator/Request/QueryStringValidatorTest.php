<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\QueryStringValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class QueryStringValidatorTest extends TestCase
{
    private QueryStringValidator $validator;
    private QueryParser $queryParser;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $this->queryParser = new QueryParser();

        $this->validator = new QueryStringValidator(
            queryParser: $this->queryParser,
            schemaValidator: $schemaValidator,
        );
    }

    #[Test]
    public function accepts_valid_querystring_parameter(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->validator->validateParameter($parameter);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejects_querystring_with_schema(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            schema: new Schema(type: 'string'),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("must use 'content' field");

        $this->validator->validateParameter($parameter);
    }

    #[Test]
    public function rejects_querystring_without_content(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("requires 'content' field");

        $this->validator->validateParameter($parameter);
    }

    #[Test]
    public function rejects_querystring_with_empty_content(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([]),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("requires 'content' field");

        $this->validator->validateParameter($parameter);
    }

    #[Test]
    public function validates_json_querystring(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                        required: ['name'],
                    ),
                ),
            ]),
        );

        $queryString = '{"name":"John"}';

        $this->validator->validate($queryString, [$parameter]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_querystring_with_integer(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'age' => new Schema(type: 'integer', minimum: 0),
                        ],
                    ),
                ),
            ]),
        );

        $queryString = '{"age":30}';

        $this->validator->validate($queryString, [$parameter]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejects_invalid_json_querystring_value(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'age' => new Schema(type: 'integer', minimum: 0),
                        ],
                    ),
                ),
            ]),
        );

        $queryString = '{"age":-5}';

        $this->expectException(MinimumError::class);

        $this->validator->validate($queryString, [$parameter]);
    }

    #[Test]
    public function rejects_missing_required_querystring(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->expectException(MissingParameterException::class);

        $this->validator->validate('', [$parameter]);
    }

    #[Test]
    public function allows_missing_optional_querystring(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            required: false,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->validator->validate('', [$parameter]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skips_non_querystring_parameters(): void
    {
        $queryParam = new Parameter(
            name: 'foo',
            in: 'query',
            schema: new Schema(type: 'string'),
        );

        $this->validator->validate('foo=bar', [$queryParam]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validates_plain_text_querystring(): void
    {
        $parameter = new Parameter(
            name: 'data',
            in: 'querystring',
            content: new Content([
                'text/plain' => new MediaType(
                    schema: new Schema(type: 'string'),
                ),
            ]),
        );

        $queryString = 'some plain text data';

        $this->validator->validate($queryString, [$parameter]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function rejects_invalid_json(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $queryString = 'not valid json';

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Malformed value');

        $this->validator->validate($queryString, [$parameter]);
    }
}
