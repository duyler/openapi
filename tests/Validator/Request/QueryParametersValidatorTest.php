<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class QueryParametersValidatorTest extends TestCase
{
    private QueryParametersValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();

        $this->validator = new QueryParametersValidator($schemaValidator, $deserializer);
    }

    #[Test]
    public function validate_query_params(): void
    {
        $queryParams = ['foo' => 'bar'];
        $parameterSchemas = [
            new Parameter(
                name: 'foo',
                in: 'query',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($queryParams, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allow_empty_value(): void
    {
        $queryParams = ['foo' => ''];
        $parameterSchemas = [
            new Parameter(
                name: 'foo',
                in: 'query',
                required: true,
                allowEmptyValue: true,
            ),
        ];

        $this->validator->validate($queryParams, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_array_param(): void
    {
        // When explode=true, query parser returns array
        $queryParams = ['tags' => ['php', 'javascript']];
        $parameterSchemas = [
            new Parameter(
                name: 'tags',
                in: 'query',
                explode: true,
                schema: new Schema(type: 'array', items: new Schema(type: 'string')),
            ),
        ];

        $this->validator->validate($queryParams, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_object_param(): void
    {
        $queryParams = ['filter' => ['name' => 'John', 'age' => '30']];
        $parameterSchemas = [
            new Parameter(
                name: 'filter',
                in: 'query',
                explode: true,
                schema: new Schema(type: 'object'),
            ),
        ];

        $this->validator->validate($queryParams, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_required_param(): void
    {
        $queryParams = [];
        $parameterSchemas = [
            new Parameter(
                name: 'required',
                in: 'query',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);

        $this->validator->validate($queryParams, $parameterSchemas);
    }
}
