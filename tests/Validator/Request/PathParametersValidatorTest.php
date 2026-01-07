<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PathParametersValidatorTest extends TestCase
{
    private PathParametersValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();

        $this->validator = new PathParametersValidator($schemaValidator, $deserializer);
    }

    #[Test]
    public function validate_required_path_param(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_param(): void
    {
        $params = [];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "id" in path');

        $this->validator->validate($params, $parameterSchemas);
    }

    #[Test]
    public function validate_with_schema(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_non_path_parameters(): void
    {
        $params = ['id' => '123'];
        $parameterSchemas = [
            new Parameter(
                name: 'query',
                in: 'query',
                required: true,
            ),
        ];

        $this->validator->validate($params, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }
}
