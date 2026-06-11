<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\QueryStringValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class QueryStringValidatorFullTest extends TestCase
{
    private QueryStringValidator $validator;

    protected function setUp(): void
    {
        $queryParser = new QueryParser();
        $schemaValidator = new SchemaValidator(new ValidatorPool(), BuiltinFormats::instance());

        $this->validator = new QueryStringValidator(
            $queryParser,
            $schemaValidator,
        );
    }

    #[Test]
    public function validate_skips_non_querystring_params(): void
    {
        $param = new Parameter(name: 'id', in: 'query');

        $this->validator->validate('id=1', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_throws_for_querystring_with_schema(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            schema: new Schema(type: 'string'),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("must use 'content' field");

        $this->validator->validate('filter=test', [$param]);
    }

    #[Test]
    public function validate_throws_for_querystring_without_content(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("requires 'content' field");

        $this->validator->validate('filter=test', [$param]);
    }

    #[Test]
    public function validate_throws_for_empty_query_string_when_required(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            required: true,
            content: new Content(
                mediaTypes: [
                    'application/json' => new MediaType(
                        schema: new Schema(type: 'string'),
                    ),
                ],
            ),
        );

        $this->expectException(MissingParameterException::class);

        $this->validator->validate('', [$param]);
    }

    #[Test]
    public function validate_passes_for_empty_query_string_when_optional(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            required: false,
            content: new Content(
                mediaTypes: [
                    'application/json' => new MediaType(
                        schema: new Schema(type: 'string'),
                    ),
                ],
            ),
        );

        $this->validator->validate('', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_validates_value_against_schema(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'text/plain' => new MediaType(
                        schema: new Schema(type: 'string'),
                    ),
                ],
            ),
        );

        $this->validator->validate('test', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_parameter_throws_for_null_schema_and_content(): void
    {
        $param = new Parameter(
            name: 'test',
            in: 'querystring',
        );

        $this->expectException(InvalidParameterException::class);

        $this->validator->validateParameter($param);
    }

    #[Test]
    public function validate_handles_non_parameter_items(): void
    {
        $this->validator->validate('test', ['not_a_parameter', 42, null]);

        $this->assertTrue(true);
    }
}
